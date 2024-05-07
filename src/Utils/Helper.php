<?php

namespace Njeaner\Router\Utils;

class Helper
{
    public static function implements(string $class, string $instanceClass): bool
    {
        return (new \ReflectionClass($class))->implementsInterface($instanceClass);
    }
}
