<?php

namespace Njeaner\Router\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use NJContainer\Container\Container;
use NJContainer\Container\Contracts\ContainerInterface as ContractsContainerInterface;
use Njeaner\Router\Exceptions\RouteException;
use Njeaner\Router\Exceptions\RouterException;
use Njeaner\Router\Router;
use Njeaner\Router\RouterInterface;
use Njeaner\Router\Routes\Route;
use Njeaner\Router\Routes\RouteInterface;
use Njeaner\Router\Tests\Callbacks\ActionSeparator;
use Njeaner\Router\Tests\Callbacks\ParamsInvoke;
use Njeaner\Router\Tests\Callbacks\RequestInvoke;
use Njeaner\Router\Tests\Callbacks\SimpleInvoke;
use Njeaner\Router\Tests\Middleware\InvokeRouteMiddleware;
use Njeaner\Router\Tests\Middleware\InvokeRouteMiddleware2;
use Njeaner\Router\Tests\Policies\InvokePolicy;
use Njeaner\Router\Tests\Policies\NotInvokablePolicy;
use Njeaner\Router\Tests\Routes\CustomRoute;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class RouterTest extends TestCase
{
    // /**
    //  * @var \UnitTester
    //  */
    // protected $tester;

    protected RouterInterface $router;

    protected ContainerInterface|ContractsContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = (new Router());
    }

    public function testNoMatchingRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage("No matching route");
        $this->router->run(new ServerRequest('get', '/undefined-route'));
    }


    public function testSimpleMatchedRoute()
    {
        $request = new ServerRequest('get', '/blog');
        $this->router->get('/blog', function () {
            return 'Hello World';
        }, 'blog.index');
        $route = $this->router->resolve($request);
        $this->assertEquals('Hello World', (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/blog/1-slug');
        $this->router->get('/blog/{id:\d+}-{slug:[a-z]+}', function (int $id, string $slug = 'Salut') {
            return $slug . ' ' . $id;
        }, 'blog.show')
            ->with('id', '\d+')->with('slug', '([a-z]+)');
        /** @var Route $route */
        $route = $this->router->resolve($request);
        $this->assertEquals('slug 1', (string) $route->run($request)->getBody());
        $this->assertEquals('slug', $route->getAttribute('slug'));
        $this->assertEquals('1', $route->getAttribute('id'));
    }

    public function testSimpleMatchedRouteWithInvokeCallbackClass()
    {
        $request = new ServerRequest('get', '/simple-invoke');
        $this->router->get('/simple-invoke', SimpleInvoke::class, 'simple.invoke');
        $route = $this->router->resolve($request);
        $this->assertEquals(SimpleInvoke::class, (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/invoke-request');
        $this->router->get('/invoke-request', RequestInvoke::class, 'simple.invoke.request');
        $route = $this->router->resolve($request);
        $request = $request->withAttribute('_route', $route);
        $this->assertEquals('simple.invoke.request', (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/simple-1-params');
        $this->router->get('/simple-{id:\d+}-{slug:([a-z]+)}', ParamsInvoke::class, 'simple.invoke.params');
        $route = $this->router->resolve($request);
        $request = $request->withAttribute('_route', $route);
        $this->assertEquals(
            json_encode([1, 'params', 'simple.invoke.params']),
            (string) $route->run($request)->getBody()
        );
    }

    public function testSimpleMatchedRouteWithStringCallback()
    {
        $request = new ServerRequest('get', '/diez-separator');
        $this->router->get(
            '/{slug:diez}-separator',
            ActionSeparator::class . '#action',
            'separator.action'
        );
        $route = $this->router->resolve($request);
        $this->assertEquals('diez', (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/arobase-separator');
        $this->router->get(
            '/{slug:arobase}-separator',
            ActionSeparator::class . '@action',
            'separator.action'
        );
        $route = $this->router->resolve($request);
        $this->assertEquals('arobase', (string) $route->run($request)->getBody());
    }

    public function testSimpleMatchedRouteWithArrayCallback()
    {
        $request = new ServerRequest('get', '/array-callback');
        $this->router->get('/{slug:array}-callback', [ActionSeparator::class, 'action']);
        $route = $this->router->resolve($request);

        $this->assertEquals('array', (string) $route->run($request)->getBody());
    }

    public function testRouterGetControllerNamespace()
    {
        $this->router->setControllerNamespace("Njeaner\Router\Tests\Callbacks");
        $request = new ServerRequest('get', '/array-callback');
        $this->router->get('/{slug:array}-callback', ['ActionSeparator', 'action']);
        $route = $this->router->resolve($request);
        $this->assertEquals('array', (string) $route->run($request)->getBody());

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage("invalid controller namespace in " . Router::class . "::setControllerNamespace");
        $this->router->setControllerNamespace("Njeaner/Router/Tests/Callbacks");
    }

    public function testComplexMatchedRoute()
    {
        $this->router->setContainer($this->container);
        $request = new ServerRequest('get', '/unresolve-callback-action');
        $this->router->get('/unresolve-callback-action', [ActionSeparator::class, 'call']);
        $route = $this->router->resolve($request);
        $this->assertInstanceOf(ContainerInterface::class, $this->router->getContainer());
        $this->assertEquals(SimpleInvoke::class, (string) $route->run($request)->getBody());
    }

    public function testComplexMatchedRouteWithoutSettingContainerMustCrash()
    {
        $request = new ServerRequest('get', '/unresolve-callback-action');
        $this->router->get('/unresolve-callback-action', [ActionSeparator::class, 'call']);
        $route = $this->router->resolve($request);
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage(
            'Can not resolve argument "simpleInvoke" pass in "' . ActionSeparator::class . '::call"'
        );
        $this->assertEquals('array', (string) $route->run($request)->getBody());
    }

    public function testRouterGetGroupedRoute()
    {
        $this->router->group('/test/uri', function (Router $router) {
            $router->get('', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            });

            $router->get('/{id:\d+}', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            });
        });

        $request = new ServerRequest('get', '/test/uri');
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/uri', (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/test/uri/1');
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/uri/1', (string) $route->run($request)->getBody());
    }

    public function testRouterGetRouteMiddleware()
    {
        /** Set String middleware */
        $this->router->middleware(InvokeRouteMiddleware::class, function (Router $router) {
            $router->get('/test', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            });

            $router->get('/test/{id:\d+}', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            });
        });

        $this->router->middlewares(
            [fn (ServerRequestInterface $request, $next) => $next($request), InvokeRouteMiddleware::class],
            function (Router $router) {
                $router->get("/multiples", fn (ServerRequestInterface $request) => $request->getUri()->getPath());
            }
        );

        $request = new ServerRequest('get', '/test');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/test/1');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/multiples');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertCount(2, $middleware = $route->getMiddlewares());
        // $this->assertEquals("I was invoked", $middleware[0]($request, $route));
        $this->assertEquals("/multiples", (string) $route->run($request)->getBody());
    }

    public function testRouterGroupedRouteWithMiddleware()
    {
        $this->router->middleware(InvokeRouteMiddleware::class, function (Router $router) {
            $router->group('/test', function (Router $router) {
                $router->get('', function (ServerRequestInterface $request) {
                    return $request->getUri()->getPath();
                });
                $router->group('/uri', function (Router $router) {
                    $router->middleware(InvokeRouteMiddleware2::class, function (Router $router) {
                        $router->get('/{id:\d+}', function (ServerRequestInterface $request) {
                            return $request->getUri()->getPath();
                        });
                    });
                });
            });
        });

        $request = new ServerRequest('get', '/test');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test', (string) $route->run($request)->getBody());
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/test/uri/1');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/uri/1', (string) $route->run($request)->getBody());
        $this->assertEquals(
            [InvokeRouteMiddleware::class, InvokeRouteMiddleware2::class],
            $route->getMiddlewares()
        );
    }


    public function testRouteMiddlewareCannotBeDuplicated()
    {
        $this->router->middleware(InvokeRouteMiddleware::class, function (Router $router) {
            $router->middleware(InvokeRouteMiddleware::class, function (Router $router) {
                $router->group('/test', function (Router $router) {
                    $router->middleware(InvokeRouteMiddleware2::class, function (Router $router) {
                        $router->get('/{id:\d+}', function (ServerRequestInterface $request) {
                            return $request->getUri()->getPath();
                        });
                    });
                });
            });
        });

        $request = new ServerRequest('get', '/test/1');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/1', (string) $route->run($request)->getBody());
        $this->assertEquals(
            [InvokeRouteMiddleware::class, InvokeRouteMiddleware2::class],
            $route->getMiddlewares()
        );
    }

    public function testRouteGetPolicy()
    {
        $this->router->middleware(InvokeRouteMiddleware::class, function (Router $router) {
            $router->get('/test', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            })->setPolicy(fn () => true);

            $router->get('/test/{id:\d+}', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            })->setPolicy(InvokePolicy::class);

            $router->get('/test-1', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            })->setPolicy(InvokePolicy::class);

            $router->get('/test/{id:\d+}-{slug:[a-z-]+}', function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            })->setPolicy([InvokePolicy::class, 'check']);

            $router->get("/test/{name:[a-zA-Z]+}", function (ServerRequestInterface $request) {
                return $request->getUri()->getPath();
            })->setPolicy(fn (string $name) => $name === "John");
        });

        $request = new ServerRequest('get', '/test');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test', (string) $route->run($request)->getBody());
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/test/1?param=1');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/1', (string) $route->run($request)->getBody());
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/test/1-slug');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/1-slug', (string) $route->run($request)->getBody());
        $this->assertEquals([InvokeRouteMiddleware::class], $route->getMiddlewares());

        $request = new ServerRequest('get', '/test/John');
        /** @var RouteInterface */
        $route = $this->router->resolve($request);
        $this->assertEquals('/test/John', (string) $route->run($request)->getBody());

        $request = new ServerRequest('get', '/test/johnny');
        $route = $this->router->resolve($request, false);
        $this->assertNull($route);

        $request = new ServerRequest('get', '/test-1');
        /** @var RouteInterface */
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Unauthorize route');
        $route = $this->router->resolve($request);
    }

    public function testResolveRoutePolicyParameters()
    {
        $router = $this->router;
        $this->container->set("preSetParam", "preset");
        $router->setContainer($this->container);
        $router->get("/resolvable-route", function () {
            return "Hello world";
        })
            ->setPolicy(function (InvokePolicy $invokePolicy, string $preSetParam, ?int $preDefinedParam = 5) {
                return true;
            });
        $this->assertEquals(
            "Hello world",
            (string) $router->run(new ServerRequest("get", "/resolvable-route"))->getBody()
        );
    }

    public function testRouteThrowPolicyParameterCanBeResolved()
    {
        $router = $this->router;
        $router->setContainer($this->container);
        $router->get("/unresolvable-route", function () {
            return "Calling this route must throw exception causing by policy function parameter";
        })
            ->setPolicy(function (string $unResolvableParam) {
                return "This function can not be processed";
            });
        $this->expectException(RouteException::class);
        $router->resolve(new ServerRequest("get", "/unresolvable-route"));
    }

    public function testRouteThrowPolicyCanNotBeResolved()
    {
        $router = $this->router;
        $router->get("/unresolvable-route", function () {
            return "Calling this route must throw exception causing by policy function parameter";
        })
            ->setPolicy([NotInvokablePolicy::class, 'call']);
        $this->expectException(RouteException::class);
        $router->resolve(new ServerRequest("get", "/unresolvable-route"));
    }

    public function testGenerateUri()
    {
        $route = (new Route('/simple-:id-:slug', ParamsInvoke::class, 'generated.route'))
            ->with('id', '\d+')
            ->with('slug', '([a-z]+)');
        $this->router->addRoute('get', $route);

        $uri = $this->router->generateUri(
            'generated.route',
            ['id' => 1, 'slug' => 'slug', 'test' => 6]
        );
        $this->assertEquals('/simple-1-slug?test=6', $uri);
    }

    public function testGenerateUriWhenRouteDoesNotExists()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            Router::class . "::generateUri can't no generate route from name \"not.exists.route\" "
        );
        $this->router->generateUri(
            'not.exists.route',
            ['id' => 1, 'slug' => 'slug', 'test' => 6]
        );
    }


    public function testGenerateUriMissARequiredParameter()
    {
        $route = (new Route('/simple-:id-:slug', ParamsInvoke::class, 'generated.route'))
            ->with('id', '\d+')
            ->with('slug', '([a-z]+)');
        $this->router->addRoute('get', $route);
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('Missing route parameter "slug"');
        $this->router->generateUri(
            'generated.route',
            ['id' => 1, 'test' => 6]
        );
    }

    public function testGenerateUriWithWrongParameter()
    {
        $route = (new Route('/simple-:id-:slug', ParamsInvoke::class, 'generated.route'))
            ->with('id', '\d+')
            ->with('slug', '([a-z]+)');
        $this->router->addRoute('get', $route);
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage(
            '"Parameter value "id" of route parameter "id" does not match required regex "\d+"'
        );

        $this->router->generateUri(
            'generated.route',
            ['id' => 'id', 'slug' => 'slug', 'test' => 6]
        );
    }

    public function testNoMatchedRoute()
    {
        $request = new ServerRequest('get', '/blog/not-matched-route');
        $this->router->get('/blog', function () {
            return 'Hello World';
        }, 'blog.index');
        $route = $this->router->resolve($request, false);
        $this->assertNull($route);
    }

    public function testImplementCustomRouteEntity()
    {
        $router = $this->router;
        $router->setRouteClassName(CustomRoute::class);
        $router->get("/:name", function ($name) {
            return "Hello " . $name;
        });
        $request = new ServerRequest('get', '/world');
        $route = $router->resolve($request);
        $this->assertInstanceOf(CustomRoute::class, $route);
    }

    public function testImplementCustomRouteEntityThrowExceptionIfRouteInterfaceIsNotImplemeted()
    {
        $router = $this->router;

        $this->expectException(RouteException::class);
        $this->expectExceptionMessage("Route class must be instance of " . RouteInterface::class);
        $router->setRouteClassName(\stdClass::class);
    }
}
