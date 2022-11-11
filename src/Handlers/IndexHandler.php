<?php

declare(strict_types=1);

namespace LqGrAphi\Handlers;

abstract class IndexHandler
{
	public static function handle(\Nette\DI\Container $container, bool $sandbox = true): void
	{
		if (\is_file($maintenance = __DIR__ . '/maintenance.php')) {
			require $maintenance;
		}

		$graphql = $container->getByType(\LqGrAphi\GraphQLHandler::class);
		$request = $container->getByType(\Nette\Http\Request::class);
		$response = $container->getByType(\Nette\Http\Response::class);

		if ($request->getMethod() === 'OPTIONS') {
			$response->setHeader('Access-Control-Allow-Origin', 'http://127.0.0.1');
			$response->setHeader('Access-Control-Allow-Methods', 'POST, GET');
			$response->setHeader('Access-Control-Max-Age', '86400');

			die;
		}

		if ($sandbox && $graphql->getDebugFlag() && $request->getMethod() === 'GET') {
			/** @var \Nette\Bridges\ApplicationLatte\LatteFactory $latteFactory */
			$latteFactory = $container->getByType(\Nette\Bridges\ApplicationLatte\LatteFactory::class);

			$compiledSandbox = $latteFactory->create()->renderToString(__DIR__ . '/apollo.sandbox.latte', ['baseUrl' => $request->getUrl()->getBaseUrl()]);

			(new \Nette\Application\Responses\TextResponse($compiledSandbox))->send($request, $response);

			die;
		}

		(new \Nette\Application\Responses\JsonResponse(
			$graphql->handle()
		))->send($request, $response);
	}
}
