<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2018 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\RouteGroupInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouterInterface;
use Slim\Middleware\ClosureMiddleware;
use Slim\Middleware\DeferredResolutionMiddleware;
use Slim\Middleware\DispatchMiddleware;

/**
 * App
 *
 * This is the primary class with which you instantiate,
 * configure, and run a Slim Framework application.
 * The \Slim\App class also accepts Slim Framework middleware.
 */
class App implements RequestHandlerInterface
{
    /**
     * Current version
     *
     * @var string
     */
    const VERSION = '4.0.0-dev';

    /**
     * Container
     *
     * @var ContainerInterface|null
     */
    private $container;

    /**
     * @var CallableResolverInterface
     */
    protected $callableResolver;

    /**
     * @var MiddlewareRunner
     */
    protected $middlewareRunner;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var array
     */
    protected $settings = [
        'httpVersion' => '1.1',
        'routerCacheFile' => null,
    ];

    /********************************************************************************
     * Constructor
     *******************************************************************************/

    /**
     * Create new application
     *
     * @param ResponseFactoryInterface  $responseFactory
     * @param ContainerInterface|null   $container
     * @param array                     $settings
     * @param CallableResolverInterface $callableResolver
     * @param RouterInterface           $router
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ContainerInterface $container = null,
        array $settings = [],
        CallableResolverInterface $callableResolver = null,
        RouterInterface $router = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->container = $container;
        $this->callableResolver = $callableResolver ?? new CallableResolver($container);
        $this->addSettings($settings);

        $this->router = $router ?? new Router($responseFactory, $this->callableResolver);
        $routerCacheFile = $this->getSetting('routerCacheFile', null);
        $this->router->setCacheFile($routerCacheFile);

        $this->middlewareRunner = new MiddlewareRunner();
        $this->addMiddleware(new DispatchMiddleware($this->router));
    }

    /**
     * @param MiddlewareInterface|string|callable $middleware
     * @return self
     */
    public function add($middleware): self
    {
        if (is_string($middleware)) {
            $middleware = new DeferredResolutionMiddleware($middleware, $this->container);
        } elseif ($middleware instanceof Closure) {
            $middleware = new ClosureMiddleware($middleware);
        } elseif (!($middleware instanceof MiddlewareInterface)) {
            throw new RuntimeException(
                'Parameter 1 of `Slim\App::add()` must be a closure or an object/class name '.
                'referencing an implementation of MiddlewareInterface.'
            );
        }

        return $this->addMiddleware($middleware);
    }

    /**
     * @param MiddlewareInterface $middleware
     * @return self
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewareRunner->add($middleware);
        return $this;
    }

    /********************************************************************************
     * Settings management
     *******************************************************************************/

    /**
     * Does app have a setting with given key?
     *
     * @param string $key
     * @return bool
     */
    public function hasSetting(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    /**
     * Get app settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get app setting with given key
     *
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getSetting(string $key, $defaultValue = null)
    {
        return $this->hasSetting($key) ? $this->settings[$key] : $defaultValue;
    }

    /**
     * Merge a key-value array with existing app settings
     *
     * @param array $settings
     */
    public function addSettings(array $settings)
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    /**
     * Add single app setting
     *
     * @param string $key
     * @param mixed $value
     */
    public function addSetting(string $key, $value)
    {
        $this->settings[$key] = $value;
    }

    /********************************************************************************
     * Getter methods
     *******************************************************************************/

    /**
     * Get container
     *
     * @return ContainerInterface|null
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get callable resolver
     *
     * @return CallableResolverInterface
     */
    public function getCallableResolver(): CallableResolverInterface
    {
        return $this->callableResolver;
    }

    /**
     * Get router
     *
     * @return RouterInterface
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /********************************************************************************
     * Router proxy methods
     *******************************************************************************/

    /**
     * Add GET route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function get(string $pattern, $callable): RouteInterface
    {
        return $this->map(['GET'], $pattern, $callable);
    }

    /**
     * Add POST route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function post(string $pattern, $callable): RouteInterface
    {
        return $this->map(['POST'], $pattern, $callable);
    }

    /**
     * Add PUT route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function put(string $pattern, $callable): RouteInterface
    {
        return $this->map(['PUT'], $pattern, $callable);
    }

    /**
     * Add PATCH route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function patch(string $pattern, $callable): RouteInterface
    {
        return $this->map(['PATCH'], $pattern, $callable);
    }

    /**
     * Add DELETE route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function delete(string $pattern, $callable): RouteInterface
    {
        return $this->map(['DELETE'], $pattern, $callable);
    }

    /**
     * Add OPTIONS route
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function options(string $pattern, $callable): RouteInterface
    {
        return $this->map(['OPTIONS'], $pattern, $callable);
    }

    /**
     * Add route for any HTTP method
     *
     * @param  string $pattern  The route URI pattern
     * @param  callable|string  $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function any(string $pattern, $callable): RouteInterface
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $callable);
    }

    /**
     * Add route with multiple methods
     *
     * @param  string[] $methods  Numeric array of HTTP method names
     * @param  string   $pattern  The route URI pattern
     * @param  callable|string    $callable The route callback routine
     *
     * @return RouteInterface
     */
    public function map(array $methods, string $pattern, $callable): RouteInterface
    {
        // Bind route callable to container, if present
        if ($this->container instanceof ContainerInterface && $callable instanceof \Closure) {
            $callable = $callable->bindTo($this->container);
        }

        /** @var Router $router */
        $router = $this->getRouter();
        $route = $router->map($methods, $pattern, $callable);

        return $route;
    }

    /**
     * Add a route that sends an HTTP redirect
     *
     * @param string              $from
     * @param string|UriInterface $to
     * @param int                 $status
     *
     * @return RouteInterface
     */
    public function redirect(string $from, $to, int $status = 302): RouteInterface
    {
        $handler = function (ServerRequestInterface $request, ResponseInterface $response) use ($to, $status) {
            return $response->withHeader('Location', (string)$to)->withStatus($status);
        };

        return $this->get($from, $handler);
    }

    /**
     * Route Groups
     *
     * This method accepts a route pattern and a callback. All route
     * declarations in the callback will be prepended by the group(s)
     * that it is in.
     *
     * @param string   $pattern
     * @param callable $callable
     *
     * @return RouteGroupInterface
     */
    public function group(string $pattern, $callable): RouteGroupInterface
    {
        $router = $this->getRouter();

        /** @var RouteGroup $group */
        $group = $router->pushGroup($pattern, $callable);
        $group($this);
        $router->popGroup();

        return $group;
    }

    /********************************************************************************
     * Runner
     *******************************************************************************/

    /**
     * Run application
     *
     * This method traverses the application middleware stack and then sends the
     * resultant Response object to the HTTP client.
     *
     * @param ServerRequestInterface $request
     */
    public function run(ServerRequestInterface $request): void
    {
        $response = $this->handle($request);
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($response);
    }

    /**
     * Handle a request
     *
     * This method traverses the application middleware stack and then returns the
     * resultant Response object.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->middlewareRunner->run($request);

        /**
         * This is to be in compliance with RFC 2616, Section 9.
         * If the incoming request method is HEAD, we need to ensure that the response body
         * is empty as the request may fall back on a GET route handler due to FastRoute's
         * routing logic which could potentially append content to the response body
         * https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
         */
        $method = strtoupper($request->getMethod());
        if ($method === 'HEAD') {
            $emptyBody = $this->responseFactory->createResponse()->getBody();
            return $response->withBody($emptyBody);
        }

        return $response;
    }
}
