<?php

namespace Njeaner\Router\Tests;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use NJContainer\Container\Container;
use NJContainer\Container\Contracts\ContainerInterface as ContractsContainerInterface;
use Njeaner\Router\Exceptions\RouteException;
use Njeaner\Router\Exceptions\UnauthorizedException;
use Njeaner\Router\Router;
use Njeaner\Router\RouterInterface;
use Njeaner\Router\Routes\PolicyInterface;
use Njeaner\Router\Routes\Route;
use Njeaner\Router\Tests\Controllers\ResolvableController;
use Njeaner\Router\Tests\Controllers\UnResolvableParamController;
use Njeaner\Router\Tests\Middleware\NotInvokableMiddleware;
use Njeaner\Router\Tests\Middleware\RedirectResponseMiddleware;
use Njeaner\Router\Tests\Policies\NotImplementingInterfacePolicy;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use stdClass;

class RouteTest extends TestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected RouterInterface $router;

    protected ContainerInterface|ContractsContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = (new Router());
    }

    public function testRouteResponseIsRedirected()
    {
        $route = (new Route('', fn () => ""))
            ->setMiddlewares([RedirectResponseMiddleware::class])
            ->setContainer($this->container);
        $this->assertEquals(
            301,
            $route->run(new ServerRequest("get", ""))->getStatusCode()
        );
        $this->assertSame($route, $route->getRequest()->getAttribute("__route"));
    }

    public function testRouteResolveResponseReturnResponseInterface()
    {
        $route = (new Route(
            '',
            function (ServerRequestInterface $request) {
                return (new RedirectResponseMiddleware())
                    ->process($request, $request->getAttribute("__route"));
            }
        ));

        $this->assertInstanceOf(
            ResponseInterface::class,
            $route->run((new ServerRequest("get", '')))
        );
    }

    public function testResolveRouteControllerWithParameter()
    {
        $route = (new Route('', ResolvableController::class))
            ->setContainer($this->container);
        $this->assertEquals("Parameter is Resolved", (string) $route->run(new ServerRequest("get", ""))->getBody());
    }

    public function testResolveRouteControllerWithParameterGetFromContainer()
    {
        $route = (new Route('', [ResolvableController::class, 'call']))
            ->setContainer($this->container->set("className", Route::class));
        $this->assertEquals(Route::class, (string) $route->run(new ServerRequest("get", ""))->getBody());
    }

    public function testThrowUnauthorizedException()
    {
        $route = (new Route('', fn () => ""))
            ->setPolicy(false);

        $this->expectException(UnauthorizedException::class);
        $route->run(new ServerRequest("get", ""));
    }

    public function testThrowControllerActionFunctionParameterTypeIsNotSpecified()
    {
        $route = new Route('', fn ($name) => $name);
        $this->expectException(RouteException::class);
        $route->run(new ServerRequest("get", ""));
    }

    public function testThrowControllerActionMethodParameterTypeIsNotSpecified()
    {
        $route = new Route('', [UnResolvableParamController::class, 'action']);
        $this->expectException(RouteException::class);
        $route->run(new ServerRequest("get", ""));
    }

    public function testThrowControllerClassDoesNotExist()
    {
        $route = new Route('', "FakeController");
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('The class "\FakeController" does not exist');
        $route->run(new ServerRequest("get", ""));
    }

    public function testThrowControllerActionMethodDoesNotExist()
    {
        $route = (new Route('', [ResolvableController::class, "undefined"]))
            ->setContainer($this->container);
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage(
            'The method "undefined" does not exist in class "' . ResolvableController::class . '"'
        );
        $route->run(new ServerRequest("get", ""));
    }

    public function testRouteCallbackThrowMustNotBeEmptyException()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage(" callback argument must no be empty");
        new Route('', []);
    }


    public function testRoutePolicyThrowNotImplementingPolicyInterfaceException()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage(
            NotImplementingInterfacePolicy::class . " class must implement "
                . PolicyInterface::class . " interface"
        );
        (new Route('', ResolvableController::class))
            ->setPolicy(NotImplementingInterfacePolicy::class);
    }

    public function testRouteMiddlewareIsNotAStringNorAnArrayNorACallback()
    {
        $this->expectException(RouteException::class);
        (new Route('', ResolvableController::class))
            ->setMiddlewares([new stdClass]);
    }

    public function testRouteMiddlewareIsInvalidArrayParam()
    {
        $this->expectException(RouteException::class);
        (new Route('', ResolvableController::class))
            ->setMiddlewares([[NotInvokableMiddleware::class, "UndefinedFunction"]]);
    }

    public function testRouteMiddlewareIsNotInvokableClass()
    {
        $this->expectException(RouteException::class);
        (new Route('', ResolvableController::class))
            ->setMiddlewares([NotInvokableMiddleware::class]);
    }
}
