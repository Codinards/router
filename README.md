# PHP Simple Fast Router

A simple PHP fast router

## Installation

```php
composer require njeaner/router
```

## How to use it

### Router and Routes Initialization

#### Initialization without ContainerInterface parameter

```php
$router = new \Njeaner\Router\Router();
```

#### Initialization with ContainerInterface parameter

For complexe projects that need a container to resolve route callback, route middlewares or route policy arguments,
you can initialize router passing an instance of **\Psr\Container\ContainerInterface**. by default, **njeaner\router** package recommand **njeaner\container-di** package that was used to process package tests; but you can use any other container package that implements **\Psr\Container\ContainerInterface**.

```php
$container = new \NJContainer\Container\Container(); // or use any other container that implements \Psr\Container\ContainerInterface.
$router = new \Njeaner\Router\Router($container);
```

### Register routes

```php
/** register get routes */
$router->get("/my-path", fn()=> "returned value when route callback is executed", "route_name");

/** register post routes */
$router->post("/my-path", fn()=> "returned value when route callback is executed", "route_name");

/** register put routes */
$router->put("/my-path", fn()=> "returned value when route callback is executed", "route_name");

/** register patch routes */
$router->patch("/my-path", fn()=> "returned value when route callback is executed", "route_name");

/** register delete routes */
$router->delete("/my-path", fn()=> "returned value when route callback is executed", "route_name");

/** register routes use any function that access \Njeaner\Router\RouteMethods enum as first argument */
$router->any(\Njeaner\Router\RouteMethods::get, "/my-path", fn()=> "returned value when route callback is executed", "route_name");
```

any of these methods beyond return and instance of **\Njeaner\Router\Routes\Route**

#### Route name

Route name argument can be null or empty, then route can will be used as route name

```php
/** In the case below, route name is not defined. "my-path" will be use as route name */
$router->delete("/my-path", fn()=> "returned value when route callback is executed");
```

#### Route path

Route path accept parameters that can be indicated using to format:

- semicolon format. In this case, **Njeaner\Router\Routes\Route::with()** function can be used to indicate regex format of this path parameter

```php
$router
    ->get("/posts/:id", fn(int $id) => Post::find($id), "post_show")
    ->with("id", "\d+");
```

- bracket format.

```php
$router
    ->get("/posts/{id:\d+}", fn(int $id) => Post::find($id), "post_show");
```

#### Route callback

Route callback accept several formats:

- callback function
- A invokable controller classname.
- A string combining controller classname and controller action name joined by @ or #.
- A array containing controller classname and controller action name

```php
/** route with callback function */
$router->get("/posts", fn() => Post::getAll(), "post_index");

/** route invokable controller classname */
$router->get("/posts", \Namespace\MyController::class, "post_index");

/** route combining controller classname and controller action name */
$router->get("/posts", "\Namespace\MyController@index", "post_index");
//              or
$router->get("/posts", "\Namespace\MyController#index", "post_index");

/** route combining controller classname and controller action name */
$router->get("/posts", ["\Namespace\MyController", "index"], "post_index");
//              or
$router->get("/posts", ["controller" => "\Namespace\MyController", 'action' => "index"], "post_index");
```

### Route resolution

**\Njeaner\Router\Router::resolve()** function allow user to process some request object to retrieve matched route previously registered in **\Njeaner\Router\Router** instance. This method accept two arguments

- an instance of **\Psr\Http\Message\ServerRequestInterface**, **_$request_**. by default, **njeaner/router** recommand **guzzlehttp/psr7** package
- an boolean argument, **_$throwException_**, that permit to return null or throw an exception if request path does not match any router register route.

```php
$router->get("/posts", fn()=>Post::getAll(), "post_index");
$router
    ->get('posts/:id', fn(int $id)=> Post::find($id), "post-show")
    ->with("id", "\d+");
$router
    ->post('posts/:id', fn($id)=> Post::find($id)->update($_POST), "post_show")
    ->with("id", "\d+");

$request = new \GuzzleHttp\Psr7\ServerRequest("get", "/posts/1");
$route = $router->resolve($request);
```

Resolving and returning matched route allow user to decide the moment that he want to process route callback function.

### Processing route callback function (run route callback)

**\Njeaner\Router\Router::run()** function allow user to process matched route callback function. This method accept one argument: an instance of **\Psr\Http\Message\ServerRequestInterface**, **_$request_**.

route callback can return any type of value (string, array, stringable object, psr7 response object), but returned value will be convert by **\Njeaner\Router\Router::run()** function to a **\Psr\Http\Message\ResponseInterface** instance

By default, **Njeaner\Router\Routes\Route** instance is able to resolve route path parameters, convert these parameters as route attributes, inject these attributes and request instance into route callback function if that is necessary.

```php
// route initialization
$router
    ->post(
        "/publication/{type:[a-z]+}-{status:\d+}",
        function(ServerRequestInterface $request, string $type, int $status){
            return Post::find(["type" => $type, "status" => $status])
                ->update($request->getParsedBody())
        },
        "publication_edition"
    );

// build request instance
$request = new \ServerRequest("post", "/publication/post-1");

// process route callback. $type value will be "post" and $status value will be 1
$router->run($request);
```

For most complex route callback arguments resolution, the injection of a container instance to router instance is required.

```php
// defining controller class
class PublicationController{

    public function __construct(private RepositoryManager $repositoryManager)
    {}

    public function index(string $type)
    {
        return $this->repositoryManager->get($type)->getAll();
    }
}

// defining repository manager class
class RepositoryManager{
    public function __construct(private \PDO $pdo)
    {}

    public function get(string $type): RepositoryInterface
    {
        // my code logic
    }
}

// container initialization
$myContainer = new \NJContainer\Container\Container(); // or nay other container
$myContainer->set(\PDO::class, $somePdoInstance); // inject pdo instance to container

$router
    ->get(
        "/publication/{type:[a-z]+}",
        "PublicationController@index", // or "PublicationController#index" or ["PublicationController", "index"]
        "publication_edition"
    )
    ->setContainer($myContainer); // Container instance will be inject during router initialization

// build request instance
$request = new \ServerRequest("get", "/publication/post");

//RepositoryManager and PublicationController instances will be automatically resolved and index method will be processed
$router->run($request);
```

### Route middlewares

route middlewares are called and processed before processing route callback function. This allow user to perform some action when some route match current request path. Route middleware can be a callable function, an invokable class, an array containing middleware classname with it method name, or a name of **\Psr\Http\Server\MiddlewareInterface** class.

middleware is set to route using **\Njeaner\Router\Router::middleware()** function or **\Njeaner\Router\Router::middlewares()** to set several route middlewares. Middleware callable function accept to arguments:

- an **_$request_** argument that is an instance of **\Psr\Http\Message\ServerRequestInterface**;
- an $next (or $handler for **\Psr\Http\Server\MiddlewareInterface**) argument that is really the matched route instance. Thus, **Njeaner\Router\Routes\Route** implements **Psr\Http\Server\RequestHandlerInterface** and is an invokable class.

**NOTE**: last middleware response is converted to **\Psr\Http\Message\ResponseInterface**.

```php
// defining invokable middleware
class InvokableMiddleware{
    public function __invoke(ServerRequestInterface $request, $next){
        // your middleware logic code
        return $next($request);
    }
}

// defining callable middleware
class CallableMiddleware{
        public function action(ServerRequestInterface $request, $next){
        // your middleware logic code
        return $next($request);
    }
}

// defining \Psr\Http\Server\MiddlewareInterface
class PsrMiddleware implement \Psr\Http\Server\MiddlewareInterface{

        public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler){
        // your middleware logic code
        return $handler->handle($request);
    }
}

// register middlewares
$router
    ->middleware(InvokableMiddleware::class, function(RouterInterface $router){
        $router->middlewares([
            PsrMiddleware::class,
            [CallableMiddleware::class, 'action'],
            fn($request, $next) => $next($request)
        ], function($router){
            $router->post(
                "/posts/{id:\d+}",
                fn(ServerRequestInterface $request, int $id) => Post::find($id)  ->update($request->getParsedBody()),
                "post_show"
            );
        })
    })

$request = new \ServerRequest("post", "/post/1");

$router->run($request);
```

### Route policy

Route policy can be a boolean value, a callable classname, a callable function or an array containing Policy classname with it callable Method. Policy allow execution of **\Njeaner\Router\Router::run()** function only if it value or processed value is **true**.

```php
$router
    ->post(
        "/posts/{id:\d+}",
        fn(ServerRequestInterface $request, int $id) => Post::find($id)->update($request->getParsedBody()),
        "post_show"
    )
    ->setPolicy(fn(int $id) => $id !== 1);

$request = new \ServerRequest("post", "/post/1");

$router->run($request);
```
