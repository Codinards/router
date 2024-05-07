<?php

namespace Njeaner\Router\Tests\Callbacks;

use Psr\Http\Message\ServerRequestInterface;

class ActionSeparator
{

    public function action(string $slug)
    {
        return $slug;
    }

    public function call(ServerRequestInterface $request, SimpleInvoke $simpleInvoke)
    {
        return $simpleInvoke;
    }
}
