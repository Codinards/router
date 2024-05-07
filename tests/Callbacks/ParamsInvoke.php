<?php

namespace Njeaner\Router\Tests\Callbacks;

use Psr\Http\Message\ServerRequestInterface;

class ParamsInvoke
{
    public function __invoke(ServerRequestInterface $request, int $id, string $slug)
    {
        return [$id, $slug, $request->getAttribute('_route')->getName()];
    }
}
