<?php

namespace LqGrAphi\Resolvers\Exceptions;

class BadRequestException extends BaseException
{
	public function __construct(string $string)
	{
		parent::__construct("Bad request: $string");
	}

	public function getCategory(): string
	{
		return (string) ExceptionCategories::BAD_REQUEST->value;
	}
}
