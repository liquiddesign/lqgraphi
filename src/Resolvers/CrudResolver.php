<?php

namespace LqGrAphi\Resolvers;

use Common\DB\IGeneralRepository;
use GraphQL\Type\Definition\ResolveInfo;
use LqGrAphi\GraphQLContext;
use LqGrAphi\Resolvers\Exceptions\BadRequestException;
use LqGrAphi\Schema\BaseType;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\Collection;
use StORM\DIConnection;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;
use StORM\Repository;

abstract class CrudResolver extends BaseResolver
{
	/** @var callable(array<mixed>, array<mixed>): array<mixed>|null */
	public $onBeforeGetOne = null;

	/** @var callable(array<mixed>, array<mixed>): array<mixed>|null */
	public $onBeforeGetAll = null;

	/** @var callable(array<mixed>, array<mixed>): array<mixed>|null */
	public $onBeforeCreate = null;

	/** @var callable(array<mixed>, array<mixed>): array<mixed>|null */
	public $onBeforeUpdate = null;

	/** @var callable(array<mixed>, array<mixed>): array<mixed>|null */
	public $onBeforeDelete = null;

	/**
	 * @var \StORM\Repository<\StORM\Entity>
	 */
	private Repository $repository;

	/**
	 * @return class-string<\StORM\Entity>
	 */
	abstract public function getClass(): string;

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>|null
	 * @throws \LqGrAphi\Resolvers\Exceptions\NotFoundException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function one(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
	{
		if ($this->onBeforeGetOne) {
			[$rootValue, $args] = \call_user_func($this->onBeforeGetOne, $rootValue, $args);
		}

		$results = $this->fetchResult($this->getRepository()->many()->where('this.' . BaseType::ID_NAME, $args[BaseType::ID_NAME]), $resolveInfo);

		return $results ? Arrays::first($results) : null;
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>|null
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function many(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
	{
		if ($this->onBeforeGetAll) {
			[$rootValue, $args] = \call_user_func($this->onBeforeGetAll, $rootValue, $args);
		}

		return $this->fetchResult($this->getRepository()->many(), $resolveInfo, $args['manyInput'] ?? null);
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>|null
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function collection(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
	{
		if ($this->onBeforeGetAll) {
			[$rootValue, $args] = \call_user_func($this->onBeforeGetAll, $rootValue, $args);
		}

		$repository = $this->getRepository();

		\assert($repository instanceof IGeneralRepository);

		return $this->fetchResult($repository->getCollection(), $resolveInfo, $args['manyInput'] ?? null);
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>|null
	 */
	public function create(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
	{
		if ($this->onBeforeCreate) {
			[$rootValue, $args] = \call_user_func($this->onBeforeCreate, $rootValue, $args);
		}

		$repository = $this->getRepository();

		[$input, $addRelations] = $this->extractRelationsFromInput($args['input']);

		try {
			$object = $repository->syncOne($input);

			foreach ($addRelations as $relationName => $values) {
				$object->{$relationName}->relate($values);
			}
		} catch (\Throwable $e) {
			if ($e->getCode() === '1452') {
				throw new BadRequestException('Invalid values in relations!');
			}

			throw new BadRequestException('Invalid values!');
		}

		return Arrays::first($this->fetchResult($repository->many()->where('this.' . BaseType::ID_NAME, $object->getPK()), $resolveInfo));
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>|null
	 */
	public function update(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
	{
		if ($this->onBeforeUpdate) {
			[$rootValue, $args] = \call_user_func($this->onBeforeUpdate, $rootValue, $args);
		}

		$repository = $this->getRepository();

		[$input, $addRelations, $removeRelations] = $this->extractRelationsFromInput($args['input']);

		try {
			$object = $repository->syncOne($input);

			foreach ($addRelations as $relationName => $values) {
				$object->{$relationName}->relate($values);
			}

			foreach ($removeRelations as $relationName => $values) {
				$object->{$relationName}->unrelate($values);
			}
		} catch (\Throwable $e) {
			if ($context->isDebugMode()) {
				if ($e->getCode() === '1452') {
					throw new BadRequestException('Invalid values in relations!');
				}

				throw new BadRequestException('Invalid values!');
			}

			throw $e;
		}

		return Arrays::first($this->fetchResult($repository->many()->where('this.' . BaseType::ID_NAME, $input[BaseType::ID_NAME]), $resolveInfo));
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 */
	public function delete(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int
	{
		if ($this->onBeforeDelete) {
			[$rootValue, $args] = \call_user_func($this->onBeforeDelete, $rootValue, $args);
		}

		return $this->getRepository()->many()->where('this.' . BaseType::ID_NAME, $args[BaseType::ID_NAME])->delete();
	}

	public function getName(): string
	{
		$reflection = new \ReflectionClass($this->getClass());

		return Strings::lower($reflection->getShortName());
	}

	/**
	 * @param array<mixed> $input
	 * @return array<mixed>
	 */
	protected function processMutationsFromInput(array $input): array
	{
		return $input;
	}

	/**
	 * @param array<mixed> $input
	 * @return array<mixed>
	 */
	protected function extractRelationsFromInput(array $input): array
	{
		$addRelations = [];
		$removeRelations = [];

		foreach ($input as $inputKey => $inputField) {
			if (Strings::startsWith($inputKey, 'add')) {
				if ($inputField !== null) {
					$name = Strings::lower(\substr($inputKey, 3));

					$addRelations[$name] = $inputField;
				}

				unset($input[$inputKey]);
			}

			if (Strings::startsWith($inputKey, 'remove')) {
				if ($inputField !== null) {
					$name = Strings::lower(\substr($inputKey, 6));

					$removeRelations[$name] = $inputField;
				}

				unset($input[$inputKey]);
			}

			if (!Strings::startsWith($inputKey, 'overwrite')) {
				continue;
			}

			if ($inputField !== null) {
				$name = Strings::lower(\substr($inputKey, 9));

				$input[$name] = $inputField;
			}

			unset($input[$inputKey]);
		}

		return [$input, $addRelations, $removeRelations];
	}

	/**
	 * @return \StORM\Repository<\StORM\Entity>
	 */
	protected function getRepository(): Repository
	{
		return $this->repository ??= $this->container->getByType(DIConnection::class)->findRepository($this->getClass());
	}

	/**
	 * @param \StORM\Collection<\StORM\Entity> $collection
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @param array<mixed>|null $manyInput
	 * @return array<mixed>
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	protected function fetchResult(Collection $collection, ResolveInfo $resolveInfo, ?array $manyInput = null): array
	{
		$fieldSelection = $resolveInfo->getFieldSelection(BaseType::MAX_DEPTH);

		try {
			$collection->filter((array) ($manyInput['filters'] ?? []));
		} catch (\Throwable $e) {
			throw new BadRequestException('Invalid filters');
		}

		$collection->orderBy([$manyInput['sort'] ?? BaseType::DEFAULT_SORT => $manyInput['order'] ?? BaseType::DEFAULT_ORDER])
			->setPage($manyInput['page'] ?? BaseType::DEFAULT_PAGE, $manyInput['limit'] ?? BaseType::DEFAULT_LIMIT);

		$result = [];

		if (!isset($fieldSelection['data'])) {
			$result = $this->fetchResultHelper($collection, $fieldSelection);
		} else {
			$result['data'] = $this->fetchResultHelper($collection, $fieldSelection['data']);
		}

		if (isset($fieldSelection['onPageCount'])) {
			if (!isset($result['data'])) {
				throw new BadRequestException('Can´t request "onPageCount" without requesting "data".');
			}

			$result['onPageCount'] = \count($result['data']);
		}

		return $result;
	}

	/**
	 * @param \StORM\Collection<\StORM\Entity> $collection
	 * @param array<mixed> $fieldSelection
	 * @param string|null $selectOriginalId
	 * @return array<mixed>
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	private function fetchResultHelper(Collection $collection, array $fieldSelection, ?string $selectOriginalId = null,): array
	{
		$objects = [];
		$structure = $collection->getRepository()->getStructure();
		$allRelations = $structure->getRelations();
		$mutationSuffix = $collection->getConnection()->getMutationSuffix();

		$relations = \array_keys(\array_filter(
			$allRelations,
			fn($value, $key): bool => isset($fieldSelection[$key]) && $fieldSelection[$key] && $value::class === Relation::class,
			\ARRAY_FILTER_USE_BOTH,
		));

		$relationCollections = \array_keys(\array_filter(
			$allRelations,
			fn($value, $key): bool => isset($fieldSelection[$key]) && $fieldSelection[$key] && $value::class === RelationNxN::class,
			\ARRAY_FILTER_USE_BOTH,
		));

		$ormFieldSelection = [BaseType::ID_NAME => 'this.' . BaseType::ID_NAME];

		foreach (\array_keys($fieldSelection) as $select) {
			if (Arrays::contains($relations, $select)) {
				$ormFieldSelection[$select] = "this.fk_$select";

				continue;
			}

			if (Arrays::contains($relationCollections, $select)) {
				continue;
			}

			if (!$column = $structure->getColumn($select)) {
				continue;
			}

			if ($column->hasMutations()) {
				$ormFieldSelection[$select] = "this.$select$mutationSuffix";

				continue;
			}

			$ormFieldSelection[$select] = "this.$select";
		}

		$collection->setSelect(($selectOriginalId ? ['originalId' => $selectOriginalId] : []) + $ormFieldSelection);

		foreach ($collection->fetchArray(\stdClass::class) as $object) {
			$objects[$object->{BaseType::ID_NAME}] = \get_object_vars($object);
		}

		$keys = \array_keys($objects);

		foreach ($relations as $relationName) {
			if (\is_bool($fieldSelection[$relationName])) {
				continue;
			}

			/** @var class-string<\StORM\Entity> $relationClassType */
			$relationClassType = $allRelations[$relationName]->getTarget();

			$relationObjects = $this->fetchResultHelper(
				$collection->getConnection()->findRepository($relationClassType)
					->many()
					->join(['relation' => $collection->getRepository()->getStructure()->getTable()->getName()], 'this.' . BaseType::ID_NAME . ' = relation.fk_' . $relationName)
					->setIndex('originalId')
					->where('relation.' . BaseType::ID_NAME, $keys),
				$fieldSelection[$relationName],
				'relation.' . BaseType::ID_NAME,
			);

			foreach ($objects as $object) {
				$objects[$object[BaseType::ID_NAME]][$relationName] = $relationObjects[$object[BaseType::ID_NAME]] ?? null;
			}
		}

		foreach ($relationCollections as $relationName) {
			if (\is_bool($fieldSelection[$relationName])) {
				continue;
			}

			/** @var \StORM\Meta\RelationNxN $relation */
			$relation = $allRelations[$relationName];

			$relationClassType = $relation->getTarget();

			$relationObjects = $this->fetchResultHelper(
				$collection->getConnection()->findRepository($relationClassType)
					->many()
					->join(['relationNxN' => $relation->getVia()], 'this.' . BaseType::ID_NAME . ' = relationNxN.' . $relation->getTargetViaKey())
					->where('relationNxN.' . $relation->getSourceViaKey(), $keys),
				$fieldSelection[$relationName],
				'relationNxN.' . $relation->getSourceViaKey(),
			);

			foreach (\array_keys($objects) as $objectKey) {
				$objects[$objectKey][$relationName] = [];
			}

			foreach ($relationObjects as $relationObject) {
				if (isset($objects[$relationObject['originalId']][$relationName])) {
					$objects[$relationObject['originalId']][$relationName][$relationObject[BaseType::ID_NAME]] = $relationObject;
				} else {
					$objects[$relationObject['originalId']][$relationName] = [$relationObject[BaseType::ID_NAME] => $relationObject];
				}
			}
		}

		return $objects;
	}
}
