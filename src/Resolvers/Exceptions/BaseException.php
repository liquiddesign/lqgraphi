<?php

namespace LqGrAphi\Resolvers\Exceptions;

use GraphQL\Error\ClientAware;

abstract class BaseException extends \Exception implements ClientAware
{
	abstract public function getCategory(): string;

	public function __construct(string $message)
	{
		parent::__construct($message, (int) $this->getCategory());
	}

	public function isClientSafe(): bool
	{
		return true;
	}
}
