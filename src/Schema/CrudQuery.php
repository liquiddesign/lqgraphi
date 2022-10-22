<?php

namespace LqGrAphi\Schema;

use Common\DB\IGeneralRepository;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use Nette\DI\Container;
use Nette\Utils\Strings;
use StORM\DIConnection;
use StORM\Repository;

/**
 * @method array onBeforeGetOne(array $rootValues, array $args)
 * @method array onBeforeGetAll(array $rootValues, array $args)
 */
abstract class CrudQuery extends BaseQuery
{
	protected TypeRegister $typeRegister;

	/**
	 * @var \StORM\Repository<\StORM\Entity>
	 */
	private Repository $repository;

	/**
	 * @return class-string<\StORM\Entity>
	 */
	abstract public function getClass(): string;

	public function __construct(protected Container $container, array $config = [])
	{
		$this->typeRegister = $this->container->getByType(TypeRegister::class);

		$baseName = $this->getName();
		$outputType = $this->getOutputType();

		\assert($outputType instanceof NullableType);

		$localConfig = [
			'fields' => [
				"{$baseName}One" => [
					'type' => $outputType,
					'args' => [
						BaseType::ID_NAME => TypeRegister::nonNull(TypeRegister::id()),
					],
				],
				"{$baseName}Many" => [
					'type' => $this->typeRegister->getManyOutputType($this->getName()),
					'args' => [
						'manyInput' => $this->typeRegister->getManyInputWithDefaultValue(),
					],
				],
			],
		];

		if ($this->getRepository() instanceof IGeneralRepository) {
			$localConfig['fields']["{$baseName}Collection"] = [
				'type' => $this->typeRegister->getManyOutputType($this->getName()),
				'args' => [
					'manyInput' => $this->typeRegister->getManyInputWithDefaultValue(),
				],
			];
		}

		$localConfig['fields'] += $this->addCustomFields($baseName);

		parent::__construct($container, $this->mergeFields($config, $localConfig));
	}

	/**
	 * @param string $baseName
	 * @return array<mixed>
	 */
	public function addCustomFields(string $baseName): array
	{
		return [];
	}

	public function getName(): string
	{
		$reflection = new \ReflectionClass($this->getClass());

		return Strings::lower($reflection->getShortName());
	}

	public function getOutputType(): Type
	{
		return $this->typeRegister->getOutputType($this->getName());
	}

	/**
	 * @return \StORM\Repository<\StORM\Entity>
	 */
	protected function getRepository(): Repository
	{
		return $this->repository ??= $this->container->getByType(DIConnection::class)->findRepository($this->getClass());
	}
}
