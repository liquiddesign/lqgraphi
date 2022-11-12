<?php

namespace LqGrAphi;

use LqGrAphi\Resolvers\CrudResolver;
use LqGrAphi\Schema\BaseInput;
use LqGrAphi\Schema\BaseOutput;
use LqGrAphi\Schema\CrudMutation;
use LqGrAphi\Schema\CrudQuery;
use LqGrAphi\Schema\TypeRegister;
use Nette;

class GeneratorScripts
{
	/**
	 * @param array<string, class-string> $outputs
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateOutputs(array $outputs, string $targetPath, string $targetNamespace): void
	{
		foreach ($outputs as $output => $classString) {
			$output = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($output));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$output}Output.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$output}Output");

			$class->setExtends(BaseOutput::class);

			$constructor = $class->addMethod('__construct');

			$constructor->addParameter('typeRegister')
				->setType(TypeRegister::class);

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('parent::__construct([');
			$constructor->addBody('	\'fields\' => $typeRegister->createOutputFieldsFromClass(' . $classString . '::class),');
			$constructor->addBody(']);');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}

	/**
	 * @param array<string, class-string> $inputs
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateCreateInputs(array $inputs, string $targetPath, string $targetNamespace): void
	{
		foreach ($inputs as $input => $classString) {
			$input = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($input));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$input}CreateInput.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$input}CreateInput");

			$class->setExtends(BaseInput::class);

			$constructor = $class->addMethod('__construct');

			$constructor->addParameter('typeRegister')
				->setType(TypeRegister::class);

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('parent::__construct([');
			$constructor->addBody('	\'fields\' => $typeRegister->createCrudCreateInputFieldsFromClass(' . $classString . '::class),');
			$constructor->addBody(']);');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}

	/**
	 * @param array<string, class-string> $inputs
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateUpdateInputs(array $inputs, string $targetPath, string $targetNamespace): void
	{
		foreach ($inputs as $input => $classString) {
			$input = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($input));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$input}UpdateInput.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$input}UpdateInput");

			$class->setExtends(BaseInput::class);

			$constructor = $class->addMethod('__construct');

			$constructor->addParameter('typeRegister')
				->setType(TypeRegister::class);

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('parent::__construct([');
			$constructor->addBody('	\'fields\' => $typeRegister->createCrudUpdateInputFieldsFromClass(' . $classString . '::class),');
			$constructor->addBody(']);');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}

	/**
	 * @param array<string, class-string> $queries
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateCrudQueries(array $queries, string $targetPath, string $targetNamespace): void
	{
		foreach ($queries as $query => $classString) {
			$query = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($query));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$query}Query.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$query}Query");

			$class->setExtends(CrudQuery::class);

			$constructor = $class->addMethod('getClass')->setReturnType('string');

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('return ' . $classString . '::class;');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}

	/**
	 * @param array<string, class-string> $mutations
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateCrudMutations(array $mutations, string $targetPath, string $targetNamespace): void
	{
		foreach ($mutations as $mutation => $classString) {
			$mutation = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($mutation));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$mutation}Mutation.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$mutation}Mutation");

			$class->setExtends(CrudMutation::class);

			$constructor = $class->addMethod('getClass')->setReturnType('string');

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('return ' . $classString . '::class;');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}

	/**
	 * @param array<string, class-string> $resolvers
	 * @param string $targetPath
	 * @param string $targetNamespace
	 */
	public static function generateCrudResolvers(array $resolvers, string $targetPath, string $targetNamespace): void
	{
		foreach ($resolvers as $resolver => $classString) {
			$resolver = Nette\Utils\Strings::firstUpper(Nette\Utils\Strings::lower($resolver));

			if (!Nette\Utils\Strings::endsWith($targetPath, '/')) {
				$targetPath .= '/';
			}

			$targetPath = "$targetPath{$resolver}Resolver.php";

			if (\is_file($targetPath)) {
				continue;
			}

			$file = new Nette\PhpGenerator\PhpFile();
			$file->addComment('This file is auto-generated.');
			$file->setStrictTypes();

			$targetNamespace = $file->addNamespace($targetNamespace);

			$class = $targetNamespace->addClass("{$resolver}Resolver");

			$class->setExtends(CrudResolver::class);

			$constructor = $class->addMethod('getClass')->setReturnType('string');

			if (!Nette\Utils\Strings::startsWith($classString, '\\')) {
				$classString = '\\' . $classString;
			}

			$constructor->addBody('return ' . $classString . '::class;');

			$printer = new Nette\PhpGenerator\Printer();

			Nette\Utils\FileSystem::write($targetPath, $printer->printFile($file));
		}
	}
}
