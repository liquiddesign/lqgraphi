<?php

namespace LqGrAphi\Resolvers\Exceptions;

enum ExceptionCategories: string
{
	case BAD_REQUEST = '400';
	case UNAUTHORIZED = '401';
	case FORBIDDEN = '403';
	case NOT_FOUND = '404';
}
