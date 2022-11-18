<?php

namespace LqGrAphi\Schema;

interface EntityOutput
{
	/**
	 * @return class-string<\StORM\Entity>
	 */
	public static function getClass(): string;
}
