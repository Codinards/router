<?php

declare(strict_types=1);

namespace Njeaner\Router;

/**
 * @author Jean Fils De Ntouoka 2 <nguimjeaner@gmail.com>
 * @version 1.0.0
 */
enum RouteMethods: string
{
    case GET = "get";
    case POST = "post";
    case PUT = "put";
    case PATCH = "patch";
    case DELETE = "delete";
}
