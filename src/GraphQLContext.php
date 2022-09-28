<?php

namespace LqGrAphi;

class GraphQLContext
{
	public function __construct(private readonly int $debugMode,)
	{
	}

	public function isDebugMode(): bool
	{
		return $this->debugMode > 0;
	}
}
