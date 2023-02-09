<?php

namespace LqGrAphi\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use LqGrAphi\Resolvers\Exceptions\BadRequestException;
use LqGrAphi\Schema\BaseType;
use Nette\DI\Container;
use Nette\Utils\Arrays;
use StORM\Collection;
use StORM\DIConnection;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;
use StORM\SchemaManager;

abstract class BaseResolver
{
	public function __construct(
		protected readonly Container $container,
		protected readonly SchemaManager $schemaManager,
		protected readonly DIConnection $connection,
	) {
	}

	/**
	 * @param \StORM\Collection<\StORM\Entity> $collection
	 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
	 * @param array{'sort': string|null, 'order': string, 'page': int|null, 'limit': int|null, 'filters': string|null}|null $manyInput
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

		$collection->setOrderBy([$manyInput['sort'] ?? BaseType::DEFAULT_SORT => $manyInput['order'] ?? BaseType::DEFAULT_ORDER])
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
	private function fetchResultHelper(Collection $collection, array $fieldSelection, ?string $selectOriginalId = null): array
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
		$selectModifiers = $collection->getModifiers()['SELECT'] ?? [];

		foreach (\array_keys($fieldSelection) as $select) {
			if (Arrays::contains($relations, $select)) {
				$relation = $allRelations[$select];

				if ($relation->isKeyHolder()) {
					$ormFieldSelection[$select] = "this.{$relation->getSourceKey()}";
				}

				continue;
			}

			if (Arrays::contains($relationCollections, $select)) {
				continue;
			}

			if (!$column = $structure->getColumn($select)) {
				if (isset($selectModifiers[$select])) {
					$ormFieldSelection[$select] = $selectModifiers[$select];
				}

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

			$relation = $allRelations[$relationName];

			$relationObjects = $this->fetchResultHelper(
				$collection->getConnection()->findRepository($relation->getTarget())
					->many()
					->join(['relation' => $collection->getRepository()->getStructure()->getTable()->getName()], "this.{$relation->getTargetKey()} = relation.{$relation->getSourceKey()}")
					->setIndex('originalId')
					->where('relation.' . BaseType::ID_NAME, $keys),
				$fieldSelection[$relationName],
				'relation.' . BaseType::ID_NAME,
			);

			if ($relation->isKeyHolder()) {
				foreach ($objects as $object) {
					$objects[$object[BaseType::ID_NAME]][$relationName] = $relationObjects[$object[BaseType::ID_NAME]] ?? null;
				}
			} else {
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
