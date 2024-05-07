<?php

declare(strict_types=1);

namespace Njeaner\Router;

use Njeaner\Router\Routes\RouteInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Jean Fils De Ntouoka 2 <nguimjeaner@gmail.com>
 * @version 1.0.0
 */
interface RouterInterface
{
    /**
     * Set a get route in the Router
     *
     * If $callback parameter is a string, it will be use Controller@calledMethod syntax
     * or Controller#calledMethod
     * @example Post@action or Post@action, call action method of Post controller;
     *
     * If $callback parameter is an array, it wil be use
     * ["controller" => "Controller", "action" => "calledMethod"] or
     * ["Controller", "calledMethod"] synthax
     * @example ["controller" => "PostController", "action" => "index"], call index method of PostController;
     * @example ["PostController", "index"], call index method of PostController;
     */
    public function get(string $path, string|array|callable $callback, ?string $name = null): RouteInterface;

    /**
     * Set a post route in the Router
     *
     * If $callback parameter is a string, it will be use Controller@calledMethod syntax
     * or Controller#calledMethod
     * @example Post@action or Post@action, call action method of Post controller;
     *
     * If $callback parameter is an array, it wil be use
     * ["controller" => "Controller", "action" => "calledMethod"] or
     * ["Controller", "calledMethod"] synthax
     * @example ["controller" => "PostController", "action" => "index"], call index method of PostController;
     * @example ["PostController", "index"], call index method of PostController;
     */
    public function post(string $path, string|array|callable $callback, ?string $name = null): RouteInterface;

    /**
     * Set a put route in the Router
     *
     * If $callback parameter is a string, it will be use Controller@calledMethod syntax
     * or Controller#calledMethod
     * @example Post@action or Post@action, call action method of Post controller;
     *
     * If $callback parameter is an array, it wil be use
     * ["controller" => "Controller", "action" => "calledMethod"] or
     * ["Controller", "calledMethod"] synthax
     * @example ["controller" => "PostController", "action" => "index"], call index method of PostController;
     * @example ["PostController", "index"], call index method of PostController;
     */
    public function put(string $path, string|array|callable $callback, ?string $name = null): RouteInterface;

    /**
     * Set a patch route in the Router
     *
     * If $callback parameter is a string, it will be use Controller@calledMethod syntax
     * or Controller#calledMethod
     * @example Post@action or Post@action, call action method of Post controller;
     *
     * If $callback parameter is an array, it wil be use
     * ["controller" => "Controller", "action" => "calledMethod"] or
     * ["Controller", "calledMethod"] synthax
     * @example ["controller" => "PostController", "action" => "index"], call index method of PostController;
     * @example ["PostController", "index"], call index method of PostController;
     */
    public function patch(string $path, string|array|callable $callback, ?string $name = null): RouteInterface;

    /**
     * Set a delete route in the Router
     *
     * If $callback parameter is a string, it will be use Controller@calledMethod syntax
     * or Controller#calledMethod
     * @example Post@action or Post@action, call action method of Post controller;
     *
     * If $callback parameter is an array, it wil be use
     * ["controller" => "Controller", "action" => "calledMethod"] or
     * ["Controller", "calledMethod"] synthax
     * @example ["controller" => "PostController", "action" => "index"], call index method of PostController;
     * @example ["PostController", "index"], call index method of PostController;
     */
    public function delete(string $path, string|array|callable $callback, ?string $name = null): RouteInterface;
    /**
     * Return any Route method
     */
    public function any(
        RouteMethods $method,
        string $url,
        string|array|callable $callback,
        ?string $name = null
    ): RouteInterface;

    /**
     * Get route by name or index
     */
    public function getRoute(string $name): ?RouteInterface;

    /**
     * Add single route to the router
     */
    public function addRoute(string $method, RouteInterface $route): RouterInterface;

    /**
     * Generate uri from route name, parameters (and fragment if present)
     * @example generateUri("post", ["id"=> 1]) can generate /post/1
     * @example generateUri("post", ["id"=> 1], "comments") can generate /post/1?#comments
     */
    public function generateUri(string $name, array $params = [], ?string $fragment = null): string;

    /**
     * group route using a callback
     */
    public function group(string $uri, callable $callback): RouterInterface;


    /**
     * Apply a middleware to group of routes
     */
    public function middleware(string|array|callable $middleware, callable $callback): RouterInterface;

    /**
     * Apply sevaerals middlewares to group of routes
     */
    public function middlewares(array $middleware, callable $callback): RouterInterface;

    /**
     * Match and run matched route
     *
     * @throws RouterException
     */
    public function run(ServerRequestInterface $request): ResponseInterface;

    /**
     * Match and rerurn matched route
     *
     * @throws RouterException
     */
    public function resolve(ServerRequestInterface $request, $throwException = true): ?RouteInterface;

    /**
     * Set class that will be use to instantiate each route instance
     *
     * @throws RouterException
     */
    public function setRouteClassName(string $className): RouterInterface;

    /**
     * Set the value of controller Namespace
     *
     */
    public function setControllerNamespace(string $controllerNamespace): RouterInterface;

    /**
     * Get the value of container
     */
    public function getContainer(): ContainerInterface;

    /**
     * Set router container instance to resolve routes dependencies
     */
    public function setContainer(ContainerInterface $container): RouterInterface;
}
