<?php

namespace LqGrAphi\Schema;

use GraphQL\Type\Definition\EnumType;

class OrderEnum extends EnumType
{
	public function __construct()
	{
		$config = [
			'values' => ['ASC', 'DESC'],
		];

		parent::__construct($config);
	}
}
