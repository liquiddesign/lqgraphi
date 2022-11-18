<?php

namespace LqGrAphi\Resolvers;

use Common\DB\IGeneralRepository;
use GraphQL\Type\Definition\ResolveInfo;
use LqGrAphi\GraphQLContext;
use LqGrAphi\Resolvers\Exceptions\BadRequestException;
use LqGrAphi\Schema\BaseType;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\DIConnection;
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
	 * @return array<mixed>
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function many(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
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
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function manyTotalCount(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int
	{
		return \count($this->many($rootValue, $args, $context, $resolveInfo));
	}

	/**
	 * @param array<mixed> $rootValue
	 * @param array<mixed> $args
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @return array<mixed>
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function collection(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
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
	 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
	 * @throws \ReflectionException
	 * @throws \StORM\Exception\GeneralException
	 */
	public function collectionTotalCount(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): int
	{
		return \count($this->collection($rootValue, $args, $context, $resolveInfo));
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
		$input = $this->processMutationsFromInput($input, $context, $repository);

		try {
			$object = $repository->createOne($input);

			foreach ($addRelations as $relationName => $values) {
				$object->{$relationName}->relate($values);
			}
		} catch (\Throwable $e) {
			if ($e->getCode() === '23000') {
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
		$input = $this->processMutationsFromInput($input, $context, $repository);

		try {
			$object = $repository->syncOne($input, ignore: false);

			foreach ($addRelations as $relationName => $values) {
				$object->{$relationName}->relate($values);
			}

			foreach ($removeRelations as $relationName => $values) {
				$object->{$relationName}->unrelate($values);
			}
		} catch (\Throwable $e) {
			if (!$context->isDebugMode()) {
				if ($e->getCode() === '23000') {
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
		$reflection = new \ReflectionClass($this);

		$className = $reflection->getShortName();

		return Strings::firstLower((string) (Strings::endsWith($className, 'Resolver') ? Strings::before($className, 'Resolver') : $className));
	}

	/**
	 * @param array<mixed> $input
	 * @param \LqGrAphi\GraphQLContext $context
	 * @param \StORM\Repository<\StORM\Entity> $repository
	 * @return array<mixed>
	 * @throws \Exception
	 */
	protected function processMutationsFromInput(array $input, GraphQLContext $context, Repository $repository): array
	{
		$structure = $repository->getStructure();
		$mutation = $context->getSelectedMutation();

		foreach ($input as $key => $value) {
			$column = $structure->getColumn($key);

			if (!$column) {
				throw new \Exception("Processing column '$key' failed on finding column in structure!");
			}

			if (!$column->hasMutations()) {
				continue;
			}

			$input[$key] = [$mutation => $value,];
		}

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
}
