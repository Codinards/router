<?php

namespace Njeaner\Router\Tests\Middleware;

class InvokeRouteMiddleware
{
    public function __invoke()
    {
        return 'Hello';
    }
}
