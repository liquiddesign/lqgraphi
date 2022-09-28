<?php

namespace LqGrAphi\Resolvers\Exceptions;

enum ExceptionCategories: int
{
	case BAD_REQUEST = 400;
	case UNAUTHENTICATED = 401;
	case FORBIDDEN = 403;
	case NOT_FOUND = 404;
}
