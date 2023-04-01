<?php

declare(strict_types=1);

namespace LqGrAphi;

use HaydenPierce\ClassFinder\ClassFinder;
use LqGrAphi\Schema\ClassInput;
use LqGrAphi\Schema\ClassOutput;
use LqGrAphi\Schema\TypeRegister;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Strings;

class LqGrAphiDI extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'resolvers' => Expect::list()->required(),
			'queriesAndMutations' => Expect::list()->required(),
			'types' => Expect::structure([
				'outputs' => Expect::list()->required(),
				'inputs' => Expect::list()->required(),
			])->required(),
		]);
	}

	public function loadConfiguration(): void
	{
		$config = (array) $this->getConfig();

		$builder = $this->getContainerBuilder();

		$graphQLHandler = $builder->addDefinition($this->prefix('graphQLHandler'))->setType(GraphQLHandler::class);
		$typeRegister = $builder->addDefinition($this->prefix('typeRegister'))->setType(TypeRegister::class);

		$graphQLHandler->addSetup('setResolversNamespaces', [$config['resolvers']]);
		$graphQLHandler->addSetup('setQueriesAndMutationsNamespaces', [$config['queriesAndMutations']]);

		foreach ($config['types']->outputs as $namespace) {
			$classes = ClassFinder::getClassesInNamespace($namespace, ClassFinder::RECURSIVE_MODE);

			foreach ($classes as $class) {
				if (!\class_exists($class)) {
					throw new \Exception("Class '$class' not found!");
				}

				$reflection = new \ReflectionClass($class);
				$typeName = $reflection->getShortName();
				$typeName = !Strings::endsWith('Output', $typeName) ? $typeName : $typeName . 'Output';

				$typeRegister->addSetup('set', [$typeName, $class]);

				$implements = \class_implements($class);

				if (!isset($implements[ClassOutput::class])) {
					continue;
				}

				$typeRegister->addSetup('setOutputByEntityClass', [$typeName, $class::getClass()]);
			}
		}

		foreach ($config['types']->inputs as $namespace) {
			$classes = ClassFinder::getClassesInNamespace($namespace, ClassFinder::RECURSIVE_MODE);

			foreach ($classes as $class) {
				if (!\class_exists($class)) {
					throw new \Exception("Class '$class' not found!");
				}

				$reflection = new \ReflectionClass($class);
				$typeName = $reflection->getShortName();
				$typeName = !Strings::endsWith('Input', $typeName) ? $typeName : $typeName . 'Output';

				$typeRegister->addSetup('set', [$typeName, $class]);

				$implements = \class_implements($class);

				if (!isset($implements[ClassInput::class])) {
					continue;
				}

				$typeRegister->addSetup('setInputByEntityClass', [$typeName, $class::getClass()]);
			}
		}
	}
}
