<?php

namespace LqGrAphi\Schema;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use MLL\GraphQLScalars\Date;
use MLL\GraphQLScalars\DateTime;
use MLL\GraphQLScalars\JSON;
use MLL\GraphQLScalars\MixedScalar;
use MLL\GraphQLScalars\NullScalar;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use SimPod\GraphQLUtils\Builder\FieldBuilder;
use SimPod\GraphQLUtils\Builder\ObjectBuilder;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;
use StORM\RelationCollection;
use StORM\SchemaManager;

class TypeRegister extends Type
{
	/**
	 * @var array<string, mixed>
	 */
	public array $types = [];

	/**
	 * @var array<string, class-string>
	 */
	public array $typesMap = [];

	public function __construct(private readonly SchemaManager $schemaManager)
	{
	}

	public function orderEnum(): OrderEnum
	{
		return $this->types['order'] ??= new OrderEnum();
	}

	public function JSON(): JSON
	{
		return $this->types['JSON'] ??= new JSON();
	}

	public function datetime(): DateTime
	{
		return $this->types['datetime'] ??= new DateTime();
	}

	public function date(): Date
	{
		return $this->types['date'] ??= new Date();
	}

	public function null(): NullScalar
	{
		return $this->types['null'] ??= new NullScalar();
	}

	public function mixed(): MixedScalar
	{
		return $this->types['mixed'] ??= new MixedScalar();
	}

	/**
	 * @param class-string<\StORM\Entity> $class
	 * @param array<string>|null $include
	 * @param array<string> $exclude
	 * @param array<string> $forceRequired
	 * @param array<string> $forceOptional
	 * @param bool $forceAllOptional
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	public function createOutputFieldsFromClass(
		string $class,
		?array $include = null,
		array $exclude = [],
		array $forceRequired = [],
		array $forceOptional = [],
		bool $forceAllOptional = false,
		bool $includeId = true
	): array {
		$reflection = new \ReflectionClass($class);
		$stormStructure = $this->schemaManager->getStructure($class);

		$fields = $includeId ? [
			BaseType::ID_NAME => static::nonNull(static::id()),
		] : [];

		foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
			$name = $property->getName();

			if ($include) {
				if (!Arrays::contains($include, $name) || Arrays::contains($exclude, $name)) {
					continue;
				}
			} else {
				if (Arrays::contains($exclude, $name)) {
					continue;
				}
			}

			/** @var \ReflectionNamedType|null $reflectionType */
			$reflectionType = $property->getType();

			if (!$reflectionType) {
				continue;
			}

			$typeName = $reflectionType->getName();

			$fields[$name] = function () use ($typeName, $property, $forceOptional, $forceRequired, $name, $forceAllOptional, $reflectionType, $class, $stormStructure) {
				$array = false;
				$type = match ($typeName) {
					'int' => static::int(),
					'float' => static::float(),
					'bool' => static::boolean(),
					'string' => static::string(),
					default => null,
				};

				$column = $stormStructure->getColumn($property->getName());

				if ($column) {
					$type = match ($column->getType()) {
						'datetime', 'timestamp' => static::datetime(),
						'date' => static::date(),
						default => $type,
					};
				}

				if ($type === null) {
					if ($typeName === RelationCollection::class) {
						$relation = $this->schemaManager->getStructure($class)->getRelation($property->getName());

						if (!$relation) {
							throw new \Exception('Fatal error! Unknown relation "' . $property->getName() . '"!');
						}

						$typeName = $relation->getTarget();
						$array = true;
					}

					$typeName = Strings::lower(Strings::substring($typeName, \strrpos($typeName, '\\') + 1));

					$type = $this->getOutputType($typeName);
				}

				$isForceRequired = Arrays::contains($forceRequired, $name);
				$isForceOptional = Arrays::contains($forceOptional, $name);

				if ($isForceRequired && $isForceOptional) {
					throw new \Exception("Property '$name' can't be forced optional and required at same time!");
				}

				if (($array || ($forceAllOptional === false && ((!$forceOptional && $forceRequired) || (!$forceOptional && !$reflectionType->allowsNull())))) && $type instanceof NullableType) {
					$type = static::nonNull($type);
				}

				if ($array) {
					\assert($type instanceof Type);

					$type = static::nonNull(static::listOf($type));
				}

				return $type;
			};
		}

		return $fields;
	}

	/**
	 * @param class-string<\StORM\Entity> $class
	 * @param array<string>|null $include
	 * @param array<string> $exclude
	 * @param array<string> $forceRequired
	 * @param array<string> $forceOptional
	 * @param bool $forceAllOptional
	 * @param bool $includeId
	 * @param bool $setDefaultValues
	 * @param \LqGrAphi\Schema\InputRelationFieldsEnum $inputRelationFieldsEnum
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	public function createInputFieldsFromClass(
		string $class,
		?array $include = null,
		array $exclude = [],
		array $forceRequired = [],
		array $forceOptional = [],
		bool $forceAllOptional = false,
		bool $includeId = true,
		bool $setDefaultValues = false,
		InputRelationFieldsEnum $inputRelationFieldsEnum = InputRelationFieldsEnum::ALL,
	): array {
		$reflection = new \ReflectionClass($class);
		$stormStructure = $this->schemaManager->getStructure($class);

		$fields = $includeId ? [
			BaseType::ID_NAME => static::nonNull(static::id()),
		] : [];

		foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
			$name = $property->getName();

			if ($include) {
				if (!Arrays::contains($include, $name) || Arrays::contains($exclude, $name)) {
					continue;
				}
			} else {
				if (Arrays::contains($exclude, $name)) {
					continue;
				}
			}

			/** @var \ReflectionNamedType|null $reflectionType */
			$reflectionType = $property->getType();

			if (!$reflectionType) {
				continue;
			}

			$typeName = $reflectionType->getName();


			$array = false;
			$type = match ($typeName) {
				'int' => static::int(),
				'float' => static::float(),
				'bool' => static::boolean(),
				'string' => static::string(),
				default => null,
			};

			$column = $stormStructure->getColumn($property->getName());

			if ($column) {
				$type = match ($column->getType()) {
					'datetime', 'timestamp' => static::datetime(),
					'date' => static::date(),
					default => $type,
				};
			}

			if ($type === null) {
				$relation = $this->schemaManager->getStructure($class)->getRelation($property->getName());

				if ($typeName === RelationCollection::class) {
					if (!$relation) {
						throw new \Exception('Fatal error! Unknown relation "' . $property->getName() . '"!');
					}

					$array = true;
				}

				$type = static::string();
			}

			$isForceRequired = Arrays::contains($forceRequired, $name);
			$isForceOptional = Arrays::contains($forceOptional, $name);

			if ($isForceRequired && $isForceOptional) {
				throw new \Exception("Property '$name' can't be forced optional and required at same time!");
			}

			if (($array ||
					($forceAllOptional === false &&
						(
							(!$isForceOptional && $isForceRequired) ||
							(!$isForceOptional && !$reflectionType->allowsNull())
						)
					)
				) && $type instanceof NullableType) {
				$type = static::nonNull($type);
			}

			if ($array) {
				\assert($type instanceof Type);

				$type = static::listOf($type);
			}

			if (isset($relation)) {
				if ($relation instanceof RelationNxN) {
					if ($inputRelationFieldsEnum === InputRelationFieldsEnum::ALL || $inputRelationFieldsEnum === InputRelationFieldsEnum::ONLY_ADD) {
						$fields['add' . Strings::firstUpper($name)] = $type;
					}

					if ($inputRelationFieldsEnum === InputRelationFieldsEnum::ALL || $inputRelationFieldsEnum === InputRelationFieldsEnum::ONLY_REMOVE) {
						$fields['remove' . Strings::firstUpper($name)] = $type;
						$fields['overwrite' . Strings::firstUpper($name)] = $type;
					}
				} elseif ($relation instanceof Relation) {
					$fields[$name] = $type;
				}
			} else {
				$fields[$name] = ['type' => $type,];

				if ($setDefaultValues) {
					$propertyDefaultValue = $property->getDefaultValue();

					if ($propertyDefaultValue !== null) {
						$fields[$name]['defaultValue'] = $propertyDefaultValue;
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * @param class-string<\StORM\Entity> $class
	 * @param array<string>|null $include
	 * @param array<string> $exclude
	 * @param array<string> $forceRequired
	 * @param array<string> $forceOptional
	 * @param bool $forceAllOptional
	 * @param bool $includeId
	 * @param bool $setDefaultValues
	 * @param \LqGrAphi\Schema\InputRelationFieldsEnum $inputRelationFieldsEnum
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	public function createCrudCreateInputFieldsFromClass(
		string $class,
		?array $include = null,
		array $exclude = [],
		array $forceRequired = [],
		array $forceOptional = [],
		bool $forceAllOptional = false,
		bool $includeId = false,
		bool $setDefaultValues = true,
		InputRelationFieldsEnum $inputRelationFieldsEnum = InputRelationFieldsEnum::ONLY_ADD,
	): array {
		return $this->createInputFieldsFromClass(
			$class,
			$include,
			$exclude,
			$forceRequired,
			$forceOptional,
			$forceAllOptional,
			$includeId,
			$setDefaultValues,
			$inputRelationFieldsEnum
		);
	}

	/**
	 * @param class-string<\StORM\Entity> $class
	 * @param array<string>|null $include
	 * @param array<string> $exclude
	 * @param array<string> $forceRequired
	 * @param array<string> $forceOptional
	 * @param bool $forceAllOptional
	 * @param bool $includeId
	 * @param bool $setDefaultValues
	 * @param \LqGrAphi\Schema\InputRelationFieldsEnum $inputRelationFieldsEnum
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	public function createCrudUpdateInputFieldsFromClass(
		string $class,
		?array $include = null,
		array $exclude = [],
		array $forceRequired = [],
		array $forceOptional = [],
		bool $forceAllOptional = true,
		bool $includeId = true,
		bool $setDefaultValues = false,
		InputRelationFieldsEnum $inputRelationFieldsEnum = InputRelationFieldsEnum::ALL,
	): array {
		return $this->createInputFieldsFromClass(
			$class,
			$include,
			$exclude,
			$forceRequired,
			$forceOptional,
			$forceAllOptional,
			$includeId,
			$setDefaultValues,
			$inputRelationFieldsEnum
		);
	}

	public function getInputType(string $name): InputType
	{
		if (!Strings::endsWith($name, 'Input')) {
			$name .= 'Input';
		}

		if (!isset($this->typesMap[$name])) {
			return $this::mixed();
		}

		$type = $this->types[$name] ??= new $this->typesMap[$name]($this);

		if (!$type instanceof BaseInput) {
			throw new \Exception("Type '$name' is not input type!");
		}

		return $type;
	}

	public function getOutputType(string $name): Type
	{
		if (!Strings::endsWith($name, 'Output')) {
			$name .= 'Output';
		}

		if (!isset($this->typesMap[$name])) {
			return $this::mixed();
		}

		$type = $this->types[$name] ??= new $this->typesMap[$name]($this);

		if (!$type instanceof BaseOutput) {
			throw new \Exception("Type '$name' is not output type!");
		}

		return $type;
	}

	public function getManyOutputType(string $name): Type
	{
		$type = $this->getOutputType($name);

		if ($type instanceof MixedScalar) {
			return $this::mixed();
		}

		if (!Strings::endsWith($name, 'ManyOutput')) {
			$name .= 'ManyOutput';
		}

		return $this->types[$name] ??= new \GraphQL\Type\Definition\ObjectType(
			ObjectBuilder::create(Strings::firstUpper($name))->setFields([
				FieldBuilder::create('data', TypeRegister::nonNull(TypeRegister::listOf($type)))->build(),
				FieldBuilder::create('onPageCount', TypeRegister::nonNull(TypeRegister::int()))->build(),
			])->build(),
		);
	}

	/**
	 * @param string $name
	 * @param class-string $class
	 */
	public function set(string $name, string $class): void
	{
		if (isset($this->typesMap[$name])) {
			throw new \Exception("Type '$name' is already registered!");
		}

		$this->typesMap[$name] = $class;
	}

	public function getManyInput(): InputObjectType
	{
		return $this->types['manyInput'] ??= new InputObjectType([
			'name' => 'ManyInput',
			'fields' => [
				'sort' => [
					'type' => $this::string(),
					'defaultValue' => BaseType::DEFAULT_SORT,
				],
				'order' => [
					'type' => $this::orderEnum(),
					'defaultValue' => BaseType::DEFAULT_ORDER,
				],
				'limit' => [
					'type' => $this::int(),
					'defaultValue' => BaseType::DEFAULT_LIMIT,
				],
				'page' => [
					'type' => $this::int(),
					'defaultValue' => BaseType::DEFAULT_PAGE,
				],
				'filters' => [
					'type' => $this::JSON(),
					'defaultValue' => null,
				],
			],
		]);
	}

	/**
	 * @param mixed|null $defaultValue
	 * @return array<mixed>
	 */
	public function getManyInputWithDefaultValue(mixed $defaultValue = null): array
	{
		return [
			'type' => $this->getManyInput(),
			'defaultValue' => $defaultValue,
		];
	}
}
