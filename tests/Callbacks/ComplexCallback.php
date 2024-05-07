<?php

namespace Njeaner\Router\Tests\Callbacks;

use Psr\Http\Message\ServerRequestInterface;

class ComplexCallback
{
    public function __construct(private SimpleInvoke $simpleInvoke)
    {
    }

    public function __invoke(ServerRequestInterface $request, int $id, string $slug)
    {
        return [$id, $slug, $request->getAttribute('_route')->getName()];
    }

    public function action(ServerRequestInterface $request, int $id, string $slug)
    {
        return [$id, $slug, $request->getAttribute('_route')->getName()];
    }
}
