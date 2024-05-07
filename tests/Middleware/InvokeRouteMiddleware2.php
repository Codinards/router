<?php

namespace Njeaner\Router\Tests\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

class InvokeRouteMiddleware2
{
    public function __invoke()
    {
        return 'Hello';
    }

    public function call(ServerRequestInterface $request)
    {
        return "Called hello";
    }
}
