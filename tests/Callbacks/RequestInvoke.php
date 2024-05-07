<?php

namespace Njeaner\Router\Tests\Callbacks;

use Psr\Http\Message\ServerRequestInterface;

class RequestInvoke
{
    public function __invoke(ServerRequestInterface $request)
    {
        return $request->getAttribute('_route')->getName();
    }
}
