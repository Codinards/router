<?php

declare(strict_types=1);

namespace Njeaner\Router\Routes;

use Exception;
use GuzzleHttp\Psr7\Response;
use Njeaner\Router\Exceptions\RouteException;
use Njeaner\Router\Exceptions\UnauthorizedException;
use Njeaner\Router\Router;
use Njeaner\Router\Utils\Helper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;

/**
 * @author Jean Fils De Ntouoka 2 <nguimjeaner@gmail.com>
 * @version 1.0.0
 */
class Route implements RouteInterface
{

    private const DEFAULT_ROUTE_ACTION = '__invoke';

    const REDIRECT_STATUS_CODE = 301;

    /**
     * Route path
     * @var string
     */
    protected string $path;

    protected string $resolvedPath;

    /**
     * Route name
     *
     * @var string
     */
    protected string $name;

    /**
     * Route callback
     *
     * @var string|array|callable
     */
    protected $callback;

    /**
     * Route default controller namespace
     *
     * @var string
     */
    protected string $controllerNamespace = "";

    /**
     * Route matched parameters
     * @example '' ['id' => 1, 'slug' => 'slug']
     *
     * @var array
     */
    protected array $matches = [];

    /**
     * Route matched parameters regex
     * @example '' ["id" => '\d+', 'slug' => '[a-z]+']
     *
     * @var array
     */
    protected array $params = [];

    /**
     * Route matched keys
     * @example '' [0 => 'id', 1=> 'slug']
     *
     * @var array
     */
    protected array $matchedKeys = [];

    /**
     * Route matched attributes
     * @example '' ['id' => 1, 'slug' => 'slug']
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * List of route middleware
     *
     * @var string[]
     */
    protected array $middlewares = [];

    /**
     * @var callable|array|bool
     */
    protected $policy = true;

    /**
     * Route callback controller
     *
     * @var string|null
     */
    protected ?string $controller = null;

    /**
     * Route callback action
     *
     * @var string|null
     */
    protected ?string $action = null;

    /**
     * @var Router
     */
    protected Router $router;

    /**
     * @var ServerRequestInterface|null
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * @var ContainerInterface
     */
    protected ?ContainerInterface $container = null;

    protected int $middlewareIndex = 0;

    protected string $responseClassname = Response::class;

    public function __construct(string $path, string|array|callable $callback, ?string $name = null)
    {
        $this->path = trim($path, '/');
        $this->name = $name ?? $this->path;
        $this->callback = $callback;
        if (!is_callable($callback)) {
            $this->resolveGivenArrayCallback($callback);
        }


        $this->resolvePath();
    }


    /**
     * Resolve a callback property to extract controller and action name
     *
     * @param string|array $callback
     * @return void
     */
    private function resolveGivenArrayCallback(string|array $callback)
    {
        $this->mustNoBeEmpty($callback, $this->getName() . " callback argument must no be empty");
        if (is_array($callback)) {
            $this->controller = $callback['controller'] ?? $callback[0];
            $this->action = $callback['action'] ?? $callback[1];
        } elseif (str_contains($callback, "@") || str_contains($callback, "#")) {
            $sep = str_contains($callback, "@") ? "@" : "#";
            $params = explode($sep, $callback, 2);
            $this->controller = $params[0];
            $this->action = $params[1];
        } else {
            $this->controller = $callback;
            $this->action = self::DEFAULT_ROUTE_ACTION;
        }
        $this->mustNoBeEmpty($this->controller, $this->getName() . " controller argument must no be empty");
        $this->mustNoBeEmpty($this->action, $this->getName() . " controller action argument must no be empty");
        return $this;
    }

    /**
     * Throw an exception if given value is empty
     *
     * ~@throws RouteException
     */
    private function mustNoBeEmpty(mixed $value, string $message = "")
    {
        if (empty($value)) {
            throw new RouteException($message);
        }
    }


    /**
     * Bind a route parameter with a regex expression
     */
    public function with(string $param, string $regex): static
    {
        $this->params[$param] = str_replace('(', '(?:', $regex);
        return $this;
    }

    protected function resolvePath(): static
    {
        $path = preg_replace_callback("#\{([a-z]+):([^{}]*)\}#", [$this, "regexMatcher"], $this->path);
        $this->resolvedPath = preg_replace_callback("#:([\w]+)#", [$this, "paramsMatcher"], $path);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $url): bool
    {
        $url = trim($url, "/");
        $regex = "#^" . $this->resolvedPath . "$#i";
        if (!preg_match($regex, $url, $matches)) {
            return false;
        }
        array_shift($matches);

        $this->matches = $matches;

        if (!empty($this->matchedKeys)) {
            $array = [];
            foreach ($this->matchedKeys as $key => $value) {
                $array[$value] = $this->matches[$key];
            }
            $this->attributes = $array;
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws RouterException
     */
    public function generateUri(array $params = [], ?string $fragment = null): string
    {
        $path = $this->getPath();
        $paramsKeys = array_keys($params);
        foreach ($this->params as $key => $value) {
            if (!in_array($key, $paramsKeys)) {
                throw new RouteException('Missing route parameter "' . $key . '"');
            }
            if (!preg_match('/' . $value . '/', (string) $params[$key])) {
                throw new RouteException(
                    '"Parameter value "' . $params[$key] . '" of route parameter "'
                        . $key . '" does not match required regex "' . $value . '"'
                );
            }
            $path = str_replace(":$key", (string) $params[$key], $path);
            unset($params[$key]);
        }

        if (!empty($params)) {
            $path = $path . '?' . http_build_query($params);
        }
        return (stripos($path, '/') !== 0 ? "/" . $path : $path) . ($fragment ? ('#' . $fragment) : "");
    }

    /**
     * Match route parameter with request path
     *
     * @param array $matches
     * @return string
     */
    private function paramsMatcher(array $matches): string
    {
        if (isset($this->params[$matches[1]])) {
            $this->matchedKeys[] = $matches[1]; // Ajout
            return "(" . $this->params[$matches[1]] . ")";
        }
        $this->params[$matches[1]] = '([^/]+)'; //Ajout
        $this->matchedKeys[] = $matches[1]; //Ajout
        return "([^/]+)";
    }

    /**
     * Match and replace route parameters
     *
     * @param array $matches
     * @return string
     */
    protected function regexMatcher(array $matches): string
    {
        if (!isset($this->params[$matches[1]])) {
            $this->with($matches[1], $matches[2]);
        }
        return ":" . $matches[1];
    }

    /**
     * @inheritDoc
     * @throws RouterException
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        $this->setRequest($request);
        $request = $this->request;
        if ($this->getPolicy() === false) {
            throw new UnauthorizedException("Unauthorized route");
        }
        if (!empty($this->middlewares)) {
            $response = $this->handle($request);
            /** return response if it is a redirection response */
            if ($this->isRedirect($response)) {
                return $response;
            }
        }
        $callback = $this->getCallback();

        if (!is_string($callback) && is_callable($callback)) {
            return $this->resolveResponse(call_user_func_array(
                $callback,
                $this->resolveBuildParams('', $callback, $request, false)
            ));
        }

        $controller = $this->controllerNamespace . "\\" . $this->controller;

        if (!\class_exists($controller)) {
            throw new RouteException("The class \"$controller\" does not exist");
        }

        try {
            $controller = new $controller();
        } catch (\ArgumentCountError  $e) {
            $this->mustHasContainer(injectionDependency: $this->controller);
            $controller = $this->container->get($this->controller);
        }

        if (!method_exists($controller, $this->action)) {
            throw new RouteException(
                'The method "' . $this->action
                    . '" does not exist in class "'
                    . (is_object($controller) ? get_class($controller) : $controller) . '"'
            );
        }

        return $this->resolveResponse(call_user_func_array(
            [$controller, $this->action],
            $this->resolveBuildParams($controller, $this->action, $request)
        ));
    }

    /**
     * Handle middleware server request
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (isset($this->middlewares[$this->middlewareIndex])) {
            $middleware = $this->middlewares[$this->middlewareIndex];
            $this->middlewareIndex++;
            if (\is_callable($middleware)) {
                $response = $middleware($request, $this);
                return $response instanceof ResponseInterface
                    ? $response : new $this->responseClassname(body: $response);
            } elseif (is_string($middleware)) {
                if (Helper::implements($middleware, MiddlewareInterface::class)) {
                    $this->mustHasContainer();
                    return $this->container
                        ->get($middleware)
                        ->process($request, $this);
                }
                if (method_exists($middleware, '__invoke')) {
                    $response = ((new $middleware)($request, $this));
                    return $response instanceof ResponseInterface
                        ? $response : new $this->responseClassname(body: $response);
                }
            }
        }
    }

    /**
     * Invoke route to process a middleware
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }

    // private function callRouteMatchingCallback(object $entity, string $method, RequestInterface $request): mixed
    // {
    //     return $this->resolveResponse(call_user_func_array(
    //         [$entity, $method],
    //         $this->resolveBuildParams($entity, $method, $request)
    //     ));
    // }

    /**
     * @return boolean
     */
    private function hasContainer(): bool
    {
        return $this->container !== null;
    }

    /**
     * @param string|null $message
     * @param string|null $injectionDependency
     * @return void
     *
     * @throws RouterException
     */
    private function mustHasContainer(
        ?string $message = null,
        ?string $injectionDependency = null
    ): void {
        $injectionDependency = $injectionDependency
            ? 'injection dependency "' . $injectionDependency . '"'
            : 'injection dependencies';
        if (!$this->hasContainer()) {
            throw new RouteException(
                $message ?? ('Missing router container property: '
                    . $injectionDependency . ' can not be resolved without router container property')
            );
        }
    }

    /**
     * @param string|object $controller
     * @param string|callable $method
     * @param ServerRequestInterface $request
     * @param boolean $is_method
     * @return array
     * @throws RouterException
     */
    private function resolveBuildParams(
        string|object $controller,
        string|callable $method,
        ServerRequestInterface $request,
        bool $is_method = true
    ): array {
        $method = $is_method ? new ReflectionMethod($controller, $method) : new \ReflectionFunction($method);
        $buildParams = [];
        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $callable = $method->getName();
            if (!$parameter->hasType()) {
                throw new RouteException(
                    $is_method ?
                        'The type of argument "' . $name . '" in "' .
                        (is_object($controller)
                            ? get_class($controller)
                            : $controller) . '::' . $callable . '" has not given'
                        : 'The type of argument "' . $name . '" in "' . $method . '" has not given'
                );
            }

            if ($parameter->getType()->getName() == ServerRequestInterface::class) {
                $buildParams[$parameter->getName()] = $request;
            } else {
                if (($value = $this->attributes[$parameter->getName()] ?? null)) {
                    $buildParams[$parameter->getName()] = $value;
                } else {
                    try {
                        $buildParams[$parameter->getName()] = $parameter->getDefaultValue();
                    } catch (ReflectionException) {
                        try {
                            $typeName = $parameter->getType()->getName();
                            if (class_exists($typeName) or interface_exists($typeName)) {
                                $this->mustHasContainer(injectionDependency: $parameter->getType()->getName());
                                $buildParams[$parameter->getName()] = $this->container->get(
                                    $parameter->getType()->getName()
                                );
                            } else {
                                $this->mustHasContainer(injectionDependency: $parameter->getName());
                                $buildParams[$parameter->getName()] = $this->container->get($parameter->getName());
                            }
                        } catch (Exception) {
                            throw new RouteException(
                                $is_method ?
                                    'Can not resolve argument "' . $name . '" pass in "'
                                    . $controller::class . '::' . $callable . '"'
                                    : 'Can not resolve argument "' . $name . '" pass in "' . $callable . '"'
                            );
                        }
                    }
                }
            }
        }
        return $buildParams;
    }


    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(String $name): mixed
    {
        return $this->attributes[$name];
    }

    /**
     * Set route default controller namespace
     *
     * @return  static
     */
    public function setControllerNamespace(string $controllerNamespace): static
    {
        $this->controllerNamespace = $controllerNamespace;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): String
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @inheritDoc
     */
    public function getCallback(): string|array|callable
    {
        return $this->callback;
    }


    /**
     * Set route dependency container resolver
     *
     * @param  ContainerInterface  $container
     *
     * @return  static
     */
    public function setContainer(?ContainerInterface $container): static
    {
        $this->container = $container;

        return $this;
    }
    /**
     * @inheritDoc
     * @throws RouteException
     */
    public function getPolicy(): bool
    {
        $policy = $this->policy;
        if (!is_bool($policy)) {
            try {
                if (!is_array($policy)) {
                    return $this->resolvePolicy($policy);
                } else {
                    try {
                        $class = $this->container ? $this->container->get($policy[0]) : new $policy[0]();
                    } catch (\Exception | \Error $e) {
                        throw new RouteException($e->getMessage());
                    }
                    return $this->resolvePolicy($class, $policy[1]);
                }
            } catch (\Exception $e) {
                throw new RouteException($e->getMessage());
            }
        }
        return $policy;
    }

    private function resolvePolicy(object|callable $callableOrClass, ?string $method = null)
    {
        $isCallable = true;
        if ($callableOrClass instanceof PolicyInterface) {
            $reflection = new \reflectionMethod($callableOrClass, $method);
            $isCallable = false;
        } else {
            $reflection = new \ReflectionFunction($callableOrClass);
        }
        $parameters = [];
        foreach ($reflection->getParameters() as $param) {
            if ($param->getType()?->getName() === ServerRequestInterface::class) {
                $parameters[$param->getName()] = $this->request;
            } elseif ($parameter = $this->getAttribute($param->getName())) {
                $parameters[$param->getName()] = $parameter;
            } elseif ($this->container !== null && $this->container->has($param->getName())) {
                $parameters[$param->getName()] = $this->container->get($param->getName());
            } elseif ($this->container !== null && $param->getType() && class_exists($param->getType()->getName())) {
                $parameters[$param->getName()] = $this->container->get($param->getType()->getName());
            } elseif ($param->isOptional() && ($value = $param->getDefaultValue())) {
                $parameters[$param->getName()] = $value;
            } else {
                throw new RouteException(
                    "Can't be resolved policy function parameter \""
                        . $param->getName() . '" for the route "' . $this->name . '"'
                );
            }
        }
        if ($isCallable) {
            return (bool) call_user_func_array($callableOrClass, $parameters);
        } else {
            return (bool) call_user_func_array([$callableOrClass, $method], $parameters);
        }
    }

    /**
     * @inheritDoc
     */
    public function setPolicy(bool|string|callable|array $policy): static
    {
        if (is_string($policy)) {
            $policy = [$policy, '__invoke'];
        }
        if (is_array($policy) && !Helper::implements($policy[0], PolicyInterface::class)) {
            throw new RouteException($policy[0] . " class must implement " . PolicyInterface::class . " interface");
        }
        $this->policy = $policy;

        return $this;
    }

    /**
     * Get route server request
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set route server request
     *
     * @return  static
     */
    public function setRequest(ServerRequestInterface $request): static
    {
        foreach ($this->getAttributes() as $attr => $value) {
            $request = $request->withAttribute($attr, $value);
        }
        $this->request = $request->withAttribute('__route', $this);

        return $this;
    }

    /**
     * @return  string[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @param  string[]|callable[]|array[]  $middlewares
     *
     * @return  static
     */
    public function setMiddlewares(array $middlewares): static
    {
        $message = 'Invalid Route middleware: route middleware must be a callable, a invokable class,
        an array of ["class", "method"] or a classname implementing '
            . MiddlewareInterface::class . " interface";

        foreach ($middlewares as $middleware) {
            if (!is_array($middleware) && !is_string($middleware) && !is_callable($middleware)) {
                throw new RouteException($message);
            } elseif (is_array($middleware) && !method_exists($middleware[0], $middleware[1])) {
                throw new RouteException($message);
            } elseif (is_string($middleware)
                && !method_exists($middleware, '__invoke')
                && !Helper::implements($middleware, MiddlewareInterface::class)
            ) {
                throw new RouteException($message);
            }
            if (!$this->hasMiddleware($middleware)) {
                $this->middlewares[] = $middleware;
            }
        }

        return $this;
    }


    /**
     * @return  static
     */
    public function setRouter(Router $router): static
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Search if a has an middleware
     *
     * @param string $middleware
     * @return boolean
     */
    public function hasMiddleware(string|array|callable $middleware)
    {
        return array_search($middleware, $this->middlewares) !== false;
    }


    private function isRedirect(ResponseInterface $response)
    {
        return $response instanceof ResponseInterface
            && in_array($response->getStatusCode(), [301, 302, 308]);
    }

    private function resolveResponse(string|array|callable|\Stringable|Response $response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if (is_array($response)) {
            return new $this->responseClassname(body: json_encode($response));
        }

        if (is_callable($response)) {
            return new $this->responseClassname(body: $response());
        }

        return new $this->responseClassname(body: (string) $response);
    }
}
