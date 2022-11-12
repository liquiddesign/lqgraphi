<?php

namespace LqGrAphi\Resolvers\Exceptions;

class UnauthorizedException extends BaseException
{
	public function __construct(string $string)
	{
		parent::__construct("Unauthorized: $string");
	}

	public function getCategory(): string
	{
		return ExceptionCategories::UNAUTHORIZED->value;
	}
}
