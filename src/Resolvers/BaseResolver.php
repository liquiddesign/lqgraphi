<?php

namespace LqGrAphi\Resolvers;

use Nette\DI\Container;

abstract class BaseResolver
{
	public function __construct(protected readonly Container $container)
	{
	}
}
