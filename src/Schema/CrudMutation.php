<?php

namespace LqGrAphi\Schema;

use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\NullableType;
use Nette\DI\Container;
use Nette\Utils\Strings;

/**
 * @method array onBeforeCreate(array $rootValues, array $args)
 * @method array onBeforeUpdate(array $rootValues, array $args)
 * @method array onBeforeDelete(array $rootValues, array $args)
 */
abstract class CrudMutation extends BaseMutation
{
	private TypeRegister $typeRegister;

	/**
	 * @return class-string<\StORM\Entity>
	 */
	abstract public function getClass(): string;

	public function __construct(protected Container $container, array $config = [])
	{
		/** @var \LqGrAphi\Schema\TypeRegister $typeRegister */
		$typeRegister = $this->container->getByType(TypeRegister::class);
		$this->typeRegister = $typeRegister;

		$baseName = $this->getName();
		$outputType = $this->getOutputType();
		$createInputType = $this->getCreateInputType();
		$updateInputType = $this->getUpdateInputType();

		\assert($outputType instanceof NullableType);
		\assert($createInputType instanceof NullableType);
		\assert($updateInputType instanceof NullableType);

		$config = $this->mergeFields($config, [
			'fields' => [
				"{$baseName}Create" => [
					'type' => TypeRegister::nonNull($outputType),
					'args' => ['input' => TypeRegister::nonNull($createInputType),],
				],
				"{$baseName}Update" => [
					'type' => TypeRegister::nonNull($outputType),
					'args' => [
						'input' => TypeRegister::nonNull($updateInputType),
						],
				],
				"{$baseName}Delete" => [
					'type' => TypeRegister::nonNull(TypeRegister::int()),
					'args' => [BaseType::ID_NAME => TypeRegister::listOf(TypeRegister::id()),],
				],
			],
		]);

		parent::__construct($container, $config);
	}

	public function getOutputType(): \GraphQL\Type\Definition\Type
	{
		return $this->typeRegister->getOutputType($this->getName(), $this->getClass());
	}

	public function getCreateInputType(): InputType
	{
		return $this->typeRegister->getInputType($this->getName() . 'Create');
	}

	public function getUpdateInputType(): InputType
	{
		return $this->typeRegister->getInputType($this->getName() . 'Update');
	}

	public function getName(): string
	{
		$reflection = new \ReflectionClass($this);

		$className = $reflection->getShortName();

		return Strings::firstLower((string) (Strings::endsWith($className, 'Mutation') ? Strings::before($className, 'Mutation') : $className));
	}
}
