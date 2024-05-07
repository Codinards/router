<?php

declare(strict_types=1);

namespace Njeaner\Router;

use Njeaner\Router\Exceptions\RouteException;
use Njeaner\Router\Exceptions\RouterException;
use Njeaner\Router\Routes\Route;
use Njeaner\Router\Routes\RouteInterface;
use Njeaner\Router\Utils\Helper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Jean Fils De Ntouoka 2 <nguimjeaner@gmail.com>
 * @version 1.0.0
 */
class Router implements RouterInterface
{

    protected array $routes = [];

    protected array $routeNames = [];

    protected ?string $controllerNamespace = null;

    protected string $routeClassName = Route::class;

    protected array $groupUri = [];

    protected array $middlewares = [];

    public function __construct(private ?ContainerInterface $container = null)
    {
    }

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
    public function get(string $path, string|array|callable $callback, ?string $name = null): RouteInterface
    {
        return $this->add('GET', $path, $callback, $name);
    }

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
    public function post(string $path, string|array|callable $callback, ?string $name = null): RouteInterface
    {
        return $this->add('POST', $path, $callback, $name);
    }

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
    public function put(string $path, string|array|callable $callback, ?string $name = null): RouteInterface
    {
        return $this->add('PUT', $path, $callback, $name);
    }

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
    public function patch(string $path, string|array|callable $callback, ?string $name = null): RouteInterface
    {
        return $this->add('PATCH', $path, $callback, $name);
    }

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
    public function delete(string $path, string|array|callable $callback, ?string $name = null): RouteInterface
    {
        return $this->add('DELETE', $path, $callback, $name);
    }
    /**
     * Return any Route method
     */
    public function any(
        RouteMethods $method,
        string $url,
        string|array|callable $callback,
        ?string $name = null
    ): RouteInterface {
        return $this->{$method->value}($url, $callback, $name);
    }

    public function addRoute(string $method, RouteInterface $route): RouterInterface
    {
        $this->routeNames[$route->getName() ?? null] = $route;
        $this->routes[$method][] = $route;
        return $this;
    }

    public function generateUri(string $name, array $params = [], ?string $fragment = null): string
    {
        if (!isset($this->routeNames[$name])) {
            throw new RouterException(__METHOD__ . " can't no generate route from name \"$name\" ");
        }
        return $this->getRoute($name)->generateUri($params, $fragment);
    }

    public function group(string $uri, callable $callback): RouterInterface
    {
        $this->addGroupUri($uri);
        $callback($this);
        $this->removeGroupUri($uri);

        return $this;
    }


    public function middleware(string|array|callable $middleware, callable $callback): RouterInterface
    {
        if (!$this->hasMiddleware($middleware)) {
            $this->middlewares[] = $middleware;
        }
        $callback($this);
        $this->removemiddleware($middleware);
        return $this;
    }

    public function middlewares(array $middlewares, callable $callback): RouterInterface
    {
        foreach ($middlewares as $middleware) {
            if (!$this->hasMiddleware($middleware)) {
                $this->middlewares[] = $middleware;
            }
        }
        $callback($this);
        foreach ($middlewares as $middleware) {
            $this->removemiddleware($middleware);
        }
        return $this;
    }

    /**
     * Run router to match the current route from request
     *
     * @throws RouterException
     */
    private function match(
        ServerRequestInterface $request,
        bool $returnRoute = true,
        bool $throwException = true
    ): null|RouteInterface|ResponseInterface {
        $url = $request->getUri()->getPath();
        $method = $request->getMethod();
        foreach ($this->routes[$method] as $route) {
            /** @var RouteInterface $route */
            if ($route->resolve($url)) {
                $route->setContainer($this->container);
                $route->setRequest($request);
                if ($route->getPolicy()) {
                    return $returnRoute ? $route : $route->run($request);
                }
                if (!$throwException) {
                    return null;
                }
                throw new RouterException("Unauthorize route");
            }
        }
        if (!$throwException) {
            return null;
        }
        throw new RouterException("No matching route");
    }

    /**
     * Match and run matched route
     *
     * @throws RouterException
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        return $this->match($request, false);
    }

    /**
     * Match and rerurn matched route
     *
     * @throws RouterException
     */
    public function resolve(ServerRequestInterface $request, $throwException = true): ?RouteInterface
    {
        return $this->match($request, true, $throwException);
    }


    /**
     * Get the value of controllerNamespace
     */
    public function getControllerNamespace()
    {
        return $this->controllerNamespace;
    }

    /**
     * Set the value of controller Namespace
     *
     * @throws RouterException
     */
    public function setControllerNamespace(string $controllerNamespace): RouterInterface
    {
        if (!preg_match("#^([A-Z][a-z]+(\\\)?)+[a-z]+$#", $controllerNamespace)) {
            throw new RouterException("invalid controller namespace in " . __METHOD__);
        }
        $this->controllerNamespace = $controllerNamespace;

        return $this;
    }


    /**
     * Get route by name or index
     */
    public function getRoute(string $name): ?RouteInterface
    {
        return $this->routeNames[$name] ?? null;
    }

    /**
     * Get list of all Route instance group by http methods
     *
     * @return  array[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Get the value of container
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set router container instance to resolve routes dependencies
     */
    public function setContainer(ContainerInterface $container): RouterInterface
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set class that will be use to instantiate each route instance
     *
     * @throws RouterException
     */
    public function setRouteClassName(string $routeClassName): RouterInterface
    {
        if (!Helper::implements($routeClassName, RouteInterface::class)) {
            throw new RouteException("Route class must be instance of " . RouteInterface::class);
        }
        $this->routeClassName = $routeClassName;
        return $this;
    }

    private function add(string $method, string $path, $callback, ?string $name): RouteInterface
    {
        $name = ($name === null) ? (is_string($callback) ? $callback : $path) : $name;

        /** @var RouteInterface */
        $route = (new $this->routeClassName($this->groupUriToString() . $path, $callback, $name))
            ->setMiddlewares($this->middlewares)
            ->setRouter($this);


        if ($this->getControllerNamespace()) {
            $route->setControllerNamespace($this->getControllerNamespace());
        }
        $this->addRoute($method, $route);

        return $route;
    }

    private function addGroupUri(string $uri): RouterInterface
    {
        $this->groupUri[] = $uri;

        return $this;
    }

    private function removeGroupUri(string $uri): RouterInterface
    {
        $groupUri = array_reverse($this->groupUri);
        $key = array_search($uri, $groupUri);
        if ($key !== false) {
            unset($groupUri[$key]);
            $this->groupUri = array_reverse($groupUri);
        }

        return $this;
    }

    private function removeMiddleware(string|array|callable $middleware): RouterInterface
    {
        $middlewares = array_reverse($this->middlewares);
        $key = array_search($middleware, $middlewares);
        if ($key !== false) {
            unset($middlewares[$key]);
            $this->middlewares = array_reverse($middlewares);
        }

        return $this;
    }

    private function groupUriToString(): string
    {
        return array_reduce($this->groupUri, fn (?string $previous, ?string $next) => $previous . $next, '');
    }

    private function hasMiddleware(string|array|callable $middleware): bool
    {
        if (is_string($middleware) || is_array($middleware)) {
            return array_search($middleware, $this->middlewares) !== false;
        }
        return false;
    }
}
