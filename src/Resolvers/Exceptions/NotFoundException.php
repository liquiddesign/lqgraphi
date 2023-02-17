<?php

namespace LqGrAphi\Resolvers\Exceptions;

class NotFoundException extends BaseException
{
	public function __construct(string $id, ?string $type = null)
	{
		parent::__construct(($type ?: 'Object') . " with uuid '$id' not found");
	}

	public function getCategory(): string
	{
		return ExceptionCategories::NOT_FOUND->value;
	}
}
