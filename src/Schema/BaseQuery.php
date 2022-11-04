<?php

namespace LqGrAphi\Schema;

use Nette\Utils\Strings;

abstract class BaseQuery extends BaseType
{
	public function getName(): string
	{
		$reflection = new \ReflectionClass($this->getClass());

		return Strings::lower($reflection->getShortName());
	}
}
