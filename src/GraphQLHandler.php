<?php

declare(strict_types=1);

namespace LqGrAphi;

use ArrayAccess;
use Closure;
use Contributte\Psr7\Psr7RequestFactory;
use GraphQL\Error\DebugFlag;
use GraphQL\Language\Parser;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use HaydenPierce\ClassFinder\ClassFinder;
use LqGrAphi\Schema\BaseMutation;
use LqGrAphi\Schema\BaseQuery;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use StORM\Connection;
use StORM\DIConnection;
use Tracy\Debugger;
use Tracy\ILogger;

class GraphQLHandler
{
	/**
	 * @var class-string
	 */
	private string $queryAndMutationsNamespace;

	/**
	 * @var class-string
	 */
	private string $resolversNamespace;

	private readonly Cache $cache;

	public function __construct(
		private readonly Container $container,
		private readonly Request $httpRequest,
		private readonly DIConnection $connection,
		Storage $storage
	) {
		$this->cache = new Cache($storage);
	}

	/**
	 * @return array<mixed>
	 * @throws \Throwable
	 */
	public function handle(): array
	{
		try {
			$psrRequest = Psr7RequestFactory::fromNette($this->httpRequest);

			$schema = $this->getCachedSchema();

			$server = new StandardServer([
				'schema' => $schema,
				'queryBatching' => true,
				'context' => $this->getContext(),
				'fieldResolver' => function ($objectValue, array $args, GraphQLContext $context, ResolveInfo $info) {
					$fieldName = $info->fieldName;

					/** @var array<string> $allAvailableResolvers */
					$allAvailableResolvers = $this->cache->load('allResolvers', function (): array {
						return ClassFinder::getClassesInNamespace($this->getResolversNamespace(), ClassFinder::RECURSIVE_MODE);
					});

					if (isset($objectValue[$fieldName]) || (\is_array($objectValue) && \array_key_exists($fieldName, $objectValue))) {
						return $objectValue[$fieldName];
					}

					/**
					 * @var class-string|null $resolverName
					 * @var string $actionName
					 */
					[$resolverName, $actionName] = $this->cache->load($fieldName, function () use ($fieldName, $allAvailableResolvers): array {
						$matchedFieldName = \preg_split('~^[^A-Z]+\K|[A-Z][^A-Z]+\K~', $fieldName, 0, \PREG_SPLIT_NO_EMPTY);

						if (!$matchedFieldName || \count($matchedFieldName) < 2) {
							return [null, null];
						}

						$resolversNamespace = $this->getResolversNamespace();

						if (!Strings::endsWith($resolversNamespace, '\\')) {
							$resolversNamespace .= '\\';
						}

						$incrementalResolverName = '';

						$matchedFieldNameCount = \count($matchedFieldName);
						$matchedResolver = null;

						for ($i = 0; $i < $matchedFieldNameCount - 1; $i++) {
							$incrementalResolverName .= $matchedFieldName[$i];

							/** @var class-string $resolverName */
							$resolverName = $resolversNamespace . Strings::firstUpper($incrementalResolverName) . 'Resolver';

							unset($matchedFieldName[$i]);

							if (!Arrays::contains($allAvailableResolvers, $resolverName)) {
								continue;
							}

							$matchedResolver = [$resolverName, Strings::firstLower(\implode('', $matchedFieldName))];
						}

						return $matchedResolver ?: [null, null];
					});

					$resolver = null;

					if ($resolverName) {
						/** @var \LqGrAphi\Resolvers\BaseResolver|null $resolver */
						$resolver = $this->container->getByType($resolverName, false);
					}

					if (!$resolver) {
						$property = null;

						if (\is_array($objectValue) || $objectValue instanceof ArrayAccess) {
							if (isset($objectValue[$fieldName])) {
								$property = $objectValue[$fieldName];
							}
						} elseif (\is_object($objectValue)) {
							if (isset($objectValue->{$fieldName})) {
								$property = $objectValue->{$fieldName};
							}
						}

						return $property instanceof Closure
							? $property($objectValue, $args, $context, $info)
							: $property;
					}

					return $resolver->{$actionName}([], $args, $context, $info);
				},
			]);

			$this->connection->getLink()->beginTransaction();

			/** @var \GraphQL\Executor\ExecutionResult $result */
			$result = $server->executePsrRequest($psrRequest);

			if ($debugFlag = $this->getDebugFlag()) {
				/** @var \StORM\Bridges\StormTracy<\stdClass>|null $stormTracy */
				$stormTracy = Debugger::getBar()->getPanel('StORM\Bridges\StormTracy');

				if ($stormTracy) {
					/** @var \StORM\Connection $connection */
					$connection = $this->container->getByType(Connection::class);

					foreach ($connection->getLog() as $logItem) {
						Debugger::log($logItem->getSql() . ':' . $logItem->getTotalTime());
					}

					Debugger::log('Storm total time:' . $stormTracy->getTotalTime());
					Debugger::log('Storm total queries:' . $stormTracy->getTotalQueries());
				} else {
					Debugger::log('Debug mode is enabled, but StormDebugBar not found!', ILogger::WARNING);
				}
			}

			$result = $result->toArray($debugFlag);

			$this->connection->getLink()->commit();

			return $result;
		} catch (\Throwable $e) {
			if ($this->connection->getLink()->inTransaction()) {
				$this->connection->getLink()->rollBack();
			}

			if ($this->container->getParameters()['debugMode'] && !$this->container->getParameters()['productionMode']) {
				throw $e;
			}

			return [
				'error' => [
					'message' => $e->getMessage(),
				],
			];
		}
	}

	public function getCachedSchema(): Schema
	{
		$cacheDir = $this->container->getParameters()['tempDir'] . '/cache/graphql';

		$cacheFilename = $cacheDir . '/cached_schema.php';

		if (!\file_exists($cacheFilename)) {
			$schemaString = SchemaPrinter::doPrint($this->getSchema());
			FileSystem::write($cacheDir . '/schema.gql', $schemaString);

			$document = Parser::parse($schemaString);
			FileSystem::write($cacheFilename, "<?php\nreturn " . \var_export(AST::toArray($document), true) . ";\n");
		} else {
			/** @var \GraphQL\Language\AST\DocumentNode $document */
			$document = AST::fromArray(require $cacheFilename);
		}

		$typeConfigDecorator = null;

		return BuildSchema::build($document, $typeConfigDecorator);
	}

	public function getSchema(): Schema
	{
		$classes = ClassFinder::getClassesInNamespace($this->getQueryAndMutationsNamespace(), ClassFinder::RECURSIVE_MODE);

		if (!$classes) {
			throw new \Exception('You need to specify at least one query or mutation!');
		}

		$queryFields = [];
		$mutationFields = [];

		foreach ($classes as $class) {
			if (!\class_exists($class)) {
				throw new \Exception("Class '$class' not found!");
			}

			$type = new $class($this->container);

			if ($type instanceof BaseQuery) {
				foreach ($type->getFields() as $field) {
					if (isset($queryFields[$field->getName()])) {
						throw new \Exception("Query '$field->name' already exists!");
					}

					$queryFields[$field->getName()] = $field;
				}

				continue;
			}

			if ($type instanceof BaseMutation) {
				foreach ($type->getFields() as $field) {
					if (isset($queryFields[$field->getName()])) {
						throw new \Exception("Mutation '$field->name' already exists!");
					}

					$mutationFields[$field->getName()] = $field;
				}

				continue;
			}

			throw new \Exception("Class '$class' is not extending BaseQuery or BaseMutation. Can´t determine type!");
		}

		$schema = [];

		if ($queryFields) {
			$schema['query'] = new ObjectType([
				'name' => 'Query',
				'fields' => $queryFields,
			]);
		}

		if ($mutationFields) {
			$schema['mutation'] = new ObjectType([
				'name' => 'Mutation',
				'fields' => $mutationFields,
			]);
		}

		return new Schema($schema);
	}

	public function getDebugFlag(): int
	{
		$debug = DebugFlag::NONE;

		if ($this->container->getParameters()['debugMode'] && !$this->container->getParameters()['productionMode']) {
			Debugger::log('Debug mode ENABLED');
			$debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
		}

		return $debug;
	}

	/**
	 * @return class-string
	 */
	public function getQueryAndMutationsNamespace(): string
	{
		return $this->queryAndMutationsNamespace;
	}

	/**
	 * @param class-string $queryAndMutationsNamespace
	 */
	public function setQueryAndMutationsNamespace(string $queryAndMutationsNamespace): void
	{
		$this->queryAndMutationsNamespace = $queryAndMutationsNamespace;
	}

	/**
	 * @return class-string
	 */
	public function getResolversNamespace(): string
	{
		return $this->resolversNamespace;
	}

	/**
	 * @param class-string $resolversNamespace
	 */
	public function setResolversNamespace(string $resolversNamespace): void
	{
		$this->resolversNamespace = $resolversNamespace;
	}

	private function getContext(): GraphQLContext
	{
		$languages = $this->connection->getAvailableMutations();

		$detectedLanguage = $this->httpRequest->detectLanguage(\array_keys($languages));

		if ($detectedLanguage) {
			$this->connection->setMutation($detectedLanguage);
		} else {
			$detectedLanguage = $this->connection->getMutation();
		}

		return new GraphQLContext(
			$this->getDebugFlag(),
			$detectedLanguage,
		);
	}
}
