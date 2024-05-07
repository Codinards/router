<?php

namespace Njeaner\Router\Tests;

use Njeaner\Router\RouteMethods;
use Njeaner\Router\Router;
use Njeaner\Router\RouterInterface;
use Njeaner\Router\Routes\RouteInterface;
use PHPUnit\Framework\TestCase;

class RouterHttpMethodsTest extends TestCase
{
    public function testRouterGetHTTPMethod()
    {
        $router = new Router();
        $route = $router->get('/post/:id-{slug:[a-zA-Z0-9-]}', fn () => "Hello World");
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertContains($route, $router->getRoutes()["GET"]);
    }

    public function testRouterPostHTTPMethod()
    {
        $router = new Router();
        $route = $router->post('/post', fn () => "Hello World");
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertContains($route, $router->getRoutes()["POST"]);
    }

    public function testRouterPUTHTTPMethod()
    {
        $router = new Router();
        $route = $router->put('/post', fn () => "Hello World");
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertContains($route, $router->getRoutes()["PUT"]);
    }

    public function testRouterPatchHTTPMethod()
    {
        $router = new Router();
        $route = $router->patch('/post', fn () => "Hello World");
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertContains($route, $router->getRoutes()["PATCH"]);
    }

    public function testRouterDeleteHTTPMethod()
    {
        $router = new Router();
        $route = $router->delete('/post', fn () => "Hello World");
        $this->assertInstanceOf(RouterInterface::class, $router);
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertContains($route, $router->getRoutes()["DELETE"]);
    }

    public function testRouterAnyHTTPMethod()
    {
        $router = new Router();
        $router->any(RouteMethods::GET, '/post/:id-{slug:[a-zA-Z0-9-]}', fn () => "Hello World");
        $router->any(RouteMethods::POST, '/post', fn () => "Hello World");
        $router->any(RouteMethods::PUT, '/post', fn () => "Hello World");
        $router->any(RouteMethods::PATCH, '/post', fn () => "Hello World");
        $router->any(RouteMethods::DELETE, '/post', fn () => "Hello World");
        $this->assertCount(5, $router->getRoutes());
        $this->assertSame(["GET", "POST", "PUT", "PATCH", "DELETE"], array_keys($router->getRoutes()));
    }
}
