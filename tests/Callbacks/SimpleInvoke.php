<?php

namespace Njeaner\Router\Tests\Callbacks;

class SimpleInvoke
{
    public function __invoke()
    {
        return SimpleInvoke::class;
    }
}
