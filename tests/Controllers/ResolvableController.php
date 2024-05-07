<?php

namespace Njeaner\Router\Tests\Controllers;

use Njeaner\Router\Routes\RouteInterface;
use Njeaner\Router\Tests\Services\ResolveControllerParam;

class ResolvableController
{
    public function __construct(private ResolveControllerParam $resolveControllerParam)
    {
    }

    public function __invoke()
    {
        return $this->resolveControllerParam->get();
    }

    public function call(string $className)
    {
        return $className;
    }
}
