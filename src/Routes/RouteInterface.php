<?php

declare(strict_types=1);

namespace Njeaner\Router\Routes;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @author Jean Fils De Ntouoka 2 <nguimjeaner@gmail.com>
 * @version 1.0.0
 */
interface RouteInterface extends RequestHandlerInterface
{

    /**
     * Resolve if route match giving URL
     */
    public function resolve(string $url): bool;

    /**
     * Bind a route parameter with a regex expression
     */
    public function with(string $param, string $regex): static;

    /**
     * Generate route url using given parameters
     */
    public function generateUri(array $params = [], ?string $fragment = null): string;

    /**
     * Run route callback
     */
    public function run(ServerRequestInterface $request): ResponseInterface;

    /**
     * Invoke route to process a middleware
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface;

    /**
     * Get all route attributes before running route match method
     */
    public function getAttributes(): array;

    /**
     * Get an route attribute before running route match method
     */
    public function getAttribute(String $name): mixed;

    /**
     * Get route path property
     */
    public function getPath(): string;

    /**
     * Get route name property
     */
    public function getName(): string;

    /**
     * Get route callback property
     */
    public function getCallback(): string|array|callable;

    /**
     * Get all route middleware to execute before calling the route callback
     */
    public function getMiddlewares(): array;


    /**
     * Set all route middleware to execute before calling the route callback
     */
    public function setMiddlewares(array $middlewares): static;

    /**
     * Resolve an route permission condition
     */
    public function getPolicy(): bool;

    /**
     * Set an route permission condition
     */
    public function setPolicy(bool|string|callable|array $policy): static;

    /**
     * Set route dependency container resolver
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * Set route resquest
     */
    public function setRequest(ServerRequestInterface $request): static;

    /**
     * Set controller base namespace
     */
    public function setControllerNamespace(string $controllerNamespace): static;
}
