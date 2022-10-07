<?php

namespace LqGrAphi;

class GraphQLContext
{
	public function __construct(private readonly int $debugMode, private readonly string $selectedMutation)
	{
	}

	public function isDebugMode(): bool
	{
		return $this->debugMode > 0;
	}

	public function getSelectedMutation(): string
	{
		return $this->selectedMutation;
	}
}
