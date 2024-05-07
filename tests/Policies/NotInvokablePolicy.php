<?php

namespace Njeaner\Router\Tests\Policies;

use Njeaner\Router\Routes\PolicyInterface;

class NotInvokablePolicy implements PolicyInterface
{
    public function __construct(string $arg)
    {
    }

    public function call()
    {
    }
}
