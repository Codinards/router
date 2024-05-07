<?php

namespace Njeaner\Router\Tests\Policies;

use Njeaner\Router\Routes\PolicyInterface;
use Psr\Http\Message\ServerRequestInterface;

class InvokePolicy implements PolicyInterface
{
    public function __invoke(ServerRequestInterface $request)
    {
        return $request->getUri()->getQuery();
    }

    public function check()
    {
        return true;
    }
}
