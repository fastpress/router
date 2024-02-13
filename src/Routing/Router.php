<?php declare(strict_types=1);
/**
 * HTTP Routing object.
 *
 * PHP version 7.0
 *
 * @category   fastpress
 *
 * @author     https://github.com/samayo
 * @copyright  Copyright (c) samayo
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 *
 * @version    0.1.0
 */
namespace Fastpress\Routing;

/**
 * HTTP Routing object.
 *
 * @category   fastpress
 *
 * @author     https://github.com/samayo
 */
class Router
{
    /** @var array $routes */
    public $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    /** @var array $patterns */
    public $patterns = [
        ':any' => '.*',
        ':id' => '[0-9]+',
        ':slug' => '[a-z-0-9\-]+',
        ':name' => '[a-zA-Z]+',
        ':url' => '[a-zA-Z0-9 \-\_\&]+',
    ];

    /** @var string REGVAL */
    const REGVAL = '/({:.+?})/';

    /**
     * Add new routes to $routes array.
     *
     * @param string $uri
     * @param callable $callable
     * @return void
     */
    public function any(string $uri, callable $callable): void
    {
        $this->addRoute('GET', $uri, $callable);
        $this->addRoute('POST', $uri, $callable);
        $this->addRoute('PUT', $uri, $callable);
        $this->addRoute('DELETE', $uri, $callable);
    }

    /**
     * Add a GET route.
     *
     * @param string $uri
     * @param callable $callable
     * @return void
     */
    public function get(string $uri, callable $callable): void
    {
        $this->addRoute('GET', $uri, $callable);
    }

    /**
     * Add a POST route.
     *
     * @param string $uri
     * @param callable $callable
     * @return void
     */
    public function post(string $uri, callable $callable): void
    {
        $this->addRoute('POST', $uri, $callable);
    }

    /**
     * Add a PUT route.
     *
     * @param string $uri
     * @param callable $callable
     * @return void
     */
    public function put(string $uri, callable $callable): void
    {
        $this->addRoute('PUT', $uri, $callable);
    }

    /**
     * Add a DELETE route.
     *
     * @param string $uri
     * @param callable $callable
     * @return void
     */
    public function delete(string $uri, callable $callable): void
    {
        $this->addRoute('DELETE', $uri, $callable);
    }

    /**
     * Add a route to $routes.
     *
     * @param string $method (GET|POST|PUT..)
     * @param string $uri foo.com/bar/tar
     * @param callable $callable the callable method ex:  ('/' function () {})
     * @return void
     */
    protected function addRoute(string $method, string $uri, callable $callable): void
    {
        array_push($this->routes[$method], [$uri => $callable]);
    }

    /**
     * Match if URL matches a route definition.
     *
     * @param array $server
     * @param array $post | to check if there is a REST method sent via post
     * @return array|bool
     */
    public function match(array $server, array $post)
    {
        $requestMethod = $server['REQUEST_METHOD'];
        $requestUri = $server['REQUEST_URI'];

        $restMethod = $this->getRestfullMethod($post);

        if (null === $restMethod && !in_array($requestMethod, array_keys($this->routes))) {
            return false;
        }

        $method = $restMethod ?: $requestMethod;

        foreach ($this->routes[$method]  as $resource) {
            $args = [];
            $route = key($resource);
            $callable = reset($resource);

            if (preg_match(self::REGVAL, $route)) {
                list($args, ,$route) = $this->parseRegexRoute($requestUri, $route);
            }

            if (!preg_match("#^$route$#", $requestUri)) {
                unset($this->routes[$method]);
                continue;
            }

            return [
                $args, $callable
            ];
        }
    }

    /**
     * Check and return a REST request (if defined).
     *
     * @param array $postVar
     * @return string|null
     */
    protected function getRestfullMethod(array $postVar): ?string
    {
        if (array_key_exists('_method', $postVar)) {
            $method = strtoupper($postVar['_method']);
            if (in_array($method, array_keys($this->routes))) {
                return $method;
            }
        }
        return null;
    }

    /**
     * Regex parser for named routes.
     *
     * @param string $requestUri
     * @param string $resource
     * @return array
     */
    protected function parseRegexRoute(string $requestUri, string $resource): array
    {
        $route = preg_replace_callback(self::REGVAL, function ($matches) {
            $patterns = $this->patterns;
            $matches[0] = str_replace(['{', '}'], '', $matches[0]);
            if (in_array($matches[0], array_keys($patterns))) {
                return  $patterns[$matches[0]];
            }
        }, $resource);

        $regUri = explode('/', $resource);
        $args = array_diff(
            array_replace(
                $regUri,
                explode('/', $requestUri)
            ),
            $regUri
        );

        return [array_values($args), $resource, $route];
    }
}
