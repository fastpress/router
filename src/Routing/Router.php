<?php

declare(strict_types=1);

namespace Fastpress\Routing;

use Fastpress\Http\Request;
use RuntimeException;

/**
 * The Router class is responsible for mapping HTTP requests to specific handler functions or methods.
 * It supports various HTTP methods, route parameters, named routes, route groups, and middleware.
 */
class Router
{
    /** @var array[] */
    protected array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];
    /** @var array<string, array> */
    private array $namedRoutes = [];
    /** @var array<string, string> */
    private array $patterns = [
        'any' => '[^/]+',
        'id' => '\d+',
        'slug' => '[a-z0-9\-]+',
        'name' => '[a-zA-Z]+', // This will match the {name} parameter
        'alpha' => '[a-zA-Z]+',
        'alphanum' => '[a-zA-Z0-9]+',
        'num' => '[0-9]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
    ];

    /** @var array<string, array> */
    private array $middleware = [];
    /** @var array[] */
    private array $groupStack = [];
    /** @var array<string, array<string, string>> */
    private array $routeConflicts = [];
    private string $lastMethod;
    private int $lastRouteIndex;


    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
    private const PARAM_PATTERN = '/{([^}]+)}/';
    private const OPTIONAL_PARAM_PATTERN = '/{([^}]+)\?}/';


    /**
     * Router constructor.
     */
    public function __construct()
    {
        foreach (self::ALLOWED_METHODS as $method) {
            $this->routes[$method] = [];
        }
    }

    /**
     * Get all registered routes.
     *
     * @return array[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Add a custom pattern for route parameters.
     *
     * @param string $name    The name of the pattern.
     * @param string $pattern The regular expression pattern.
     * @return $this
     * @throws RuntimeException If the pattern name already exists.
     */
    public function addPattern(string $name, string $pattern): self
    {
        if (isset($this->patterns[$name])) {
            throw new RuntimeException("Pattern '{$name}' already exists");
        }
        $this->patterns[$name] = $pattern;
        return $this;
    }

    /**
     * Define a route group with shared attributes.
     *
     * @param array    $attributes The attributes to apply to the group (e.g., prefix, middleware).
     * @param callable $callback   The callback function that defines the routes within the group.
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Add middleware to the last added route.
     *
     * @param string|array $middleware The middleware to add.
     * @return $this
     */
    public function middleware(string|array $middleware): self
    {
        $middleware = (array) $middleware;
        $lastRoute = $this->getLastAddedRoute();
        if ($lastRoute) {
            $lastRoute['middleware'] = array_merge(
                $lastRoute['middleware'] ?? [],
                $middleware
            );
        }
        return $this;
    }

    /**
     * Add constraints to the parameters of the last added route.
     *
     * @param array<string, string> $constraints The constraints to apply.
     * @return $this
     */
    public function where(array $constraints): self
    {
        $lastRoute = $this->getLastAddedRoute();
        if ($lastRoute) {
            foreach ($constraints as $param => $pattern) {
                $this->addPattern("custom_{$param}", $pattern);
                $lastRoute['constraints'][$param] = "custom_{$param}";
            }
        }
        return $this;
    }

    /**
     * Get the last added route.
     *
     * @return array|null
     */
    private function getLastAddedRoute(): ?array
    {
        if (!isset($this->lastMethod, $this->lastRouteIndex)) {
            return null;
        }
        return $this->routes[$this->lastMethod][$this->lastRouteIndex] ?? null;
    }


    /**
     * Add a GET route.
     *
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     */
    public function get(string $uri, callable|string $handler): self
    {
        return $this->addRoute('GET', $uri, $handler);
    }

    /**
     * Add a POST route.
     *
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     */
    public function post(string $uri, callable|string $handler): self
    {
        return $this->addRoute('POST', $uri, $handler);
    }

    /**
     * Add a PUT route.
     *
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     */
    public function put(string $uri, callable|string $handler): self
    {
        return $this->addRoute('PUT', $uri, $handler);
    }

    /**
     * Add a DELETE route.
     *
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     */
    public function delete(string $uri, callable|string $handler): self
    {
        return $this->addRoute('DELETE', $uri, $handler);
    }

    /**
     * Add a route that matches any HTTP method.
     *
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     */
    public function any(string $uri, callable|string $handler): self
    {
        foreach (self::ALLOWED_METHODS as $method) {
            $this->addRoute($method, $uri, $handler);
        }
        return $this;
    }

    /**
     * Assign a name to the last added route.
     *
     * @param string $name The name of the route.
     * @return $this
     * @throws RuntimeException If the route name already exists or no route is defined.
     */
    public function name(string $name): self
    {
        if (isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route name '{$name}' already exists");
        }

        $route = $this->getLastAddedRoute();
        if (!$route) {
            throw new RuntimeException('No route defined to name');
        }

        $this->namedRoutes[$name] = $route;
        return $this;
    }

    /**
     * Add a route to the router.
     *
     * @param string          $method  The HTTP method.
     * @param string          $uri     The URI of the route.
     * @param callable|string $handler The handler function or method.
     * @return $this
     * @throws RuntimeException If a route conflict is detected.
     */
    private function addRoute(string $method, string $uri, callable|string $handler): self
    {
        // Apply group attributes
        if (!empty($this->groupStack)) {
            $group = end($this->groupStack);
            $uri = trim($group['prefix'] ?? '', '/') . '/' . trim($uri, '/');
            $middleware = $group['middleware'] ?? [];
        }

        // Normalize URI
        $uri = '/' . trim($uri, '/');

        // Check for route conflicts
        $signature = $this->getRouteSignature($uri);
        if (isset($this->routeConflicts[$method][$signature])) {
            throw new RuntimeException(
                "Route conflict detected for {$method} {$uri}" .
                " with existing route " . $this->routeConflicts[$method][$signature]
            );
        }

        // Store route
        $route = [
            'uri' => $uri,
            'handler' => $handler,
            'pattern' => $this->compilePattern($uri),
            'middleware' => $middleware ?? [],
            'constraints' => [],
            'parameters' => $this->extractParameters($uri)
        ];

        $this->routes[$method][] = $route;

        // Store the last added method and index
        $this->lastMethod = $method;
        $this->lastRouteIndex = array_key_last($this->routes[$method]);

        return $this;
    }

    /**
     * Get the route signature by replacing parameter names with a generic placeholder.
     *
     * @param string $uri The URI of the route.
     * @return string
     */
    private function getRouteSignature(string $uri): string
    {
        return preg_replace(self::PARAM_PATTERN, '{param}', $uri);
    }

    /**
     * Extract the parameters from the URI.
     *
     * @param string $uri The URI of the route.
     * @return array<string, array{optional:bool, name:string}>
     */
    private function extractParameters(string $uri): array
    {
        $params = [];

        // Extract optional parameters
        preg_match_all(self::OPTIONAL_PARAM_PATTERN, $uri, $matches);
        foreach ($matches[1] as $param) {
            $param = rtrim($param, '?');
            $params[$param] = [
                'optional' => true,
                'name' => $param
            ];
        }

        // Extract required parameters
        preg_match_all(self::PARAM_PATTERN, $uri, $matches);
        foreach ($matches[1] as $param) {
            // Skip if already processed as optional
            if (isset($params[$param])) {
                continue;
            }
            $params[$param] = [
                'optional' => false,
                'name' => $param
            ];
        }

        return $params;
    }


    /**
     * Compile the URI into a regular expression pattern.
     *
     * @param string $uri The URI of the route.
     * @return string
     */
    private function compilePattern(string $uri): string
    {
        // Split URI into path and query parts
        $parts = explode('?', $uri, 2);
        $path = $parts[0];
        $query = $parts[1] ?? '';

        // Process optional parameters
        $path = preg_replace_callback(
            self::OPTIONAL_PARAM_PATTERN,
            function ($matches) {
                $param = rtrim($matches[1], '?');
                $pattern = $this->patterns[$param] ?? '[^/]+';
                return "(?:/($pattern))?";
            },
            $path
        );

        // Process required parameters
        $path = preg_replace_callback(
            self::PARAM_PATTERN,
            function ($matches) {
                $param = $matches[1];
                // Skip optional parameters already processed
                if (substr($param, -1) === '?') {
                    return $matches[0];
                }
                $pattern = $this->patterns[$param] ?? '[^/]+';
                return "($pattern)";
            },
            $path
        );

        // Ensure leading slash
        $path = '/' . ltrim($path, '/');

        return "#^" . $path . "$#";
    }




    /**
     * Match the request URI to a registered route.
     *
     * @param array $server The server variables array.
     * @param array $post   The post data array.
     * @return array|null An array containing the route parameters and handler if a match is found, null otherwise.
     */
    public function match(array $server, array $post = []): ?array
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->parseRequestUri($server['REQUEST_URI'] ?? '/');



        if (!isset($this->routes[$method])) {

            return null;
        }

        foreach ($this->routes[$method] as $route) {



            $params = $this->matchRoute($route, $uri);
            if ($params !== null) {
                return [
                    'params' => $params,
                    'handler' => $route['handler']
                ];
            }
        }


        return null;
    }


    /**
     * Match the request URI to a specific route.
     *
     * @param array  $route       The route to match.
     * @param string $requestUri The request URI.
     * @return array|null An array of parameters if the route matches, null otherwise.
     */
    private function matchRoute(array $route, string $requestUri): ?array
    {

        if (!preg_match($route['pattern'], $requestUri, $matches)) {

            return null;
        } else {


        }

        array_shift($matches);

        // Extract parameter names
        $params = [];
        foreach ($route['parameters'] as $name => $config) {
            $value = array_shift($matches);
            if ($value !== null || !$config['optional']) {
                $params[$name] = urldecode($value);
            }
        }

        // Validate constraints
        foreach ($route['constraints'] as $param => $pattern) {
            if (isset($params[$param]) &&
                !preg_match("#^{$this->patterns[$pattern]}$#", $params[$param])) {
                return null;
            }
        }

        return $params;
    }



    /**
     * Parse the request URI and remove unnecessary parts.
     *
     * @param string $requestUri The request URI.
     * @return string
     */
    private function parseRequestUri(string $requestUri): string
    {
        // Remove query string
        $uri = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        // Remove multiple slashes
        $uri = preg_replace('#/{2,}#', '/', $uri);

        // Remove trailing slash unless root
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /**
     * Get the URI of a named route.
     *
     * @param string $name   The name of the route.
     * @param array  $params An array of parameters to substitute in the URI.
     * @return string|null The URI of the route if found, null otherwise.
     * @throws RuntimeException If the named route is not found or a required parameter is missing.
     */
    public function getNamedRoute(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Named route '{$name}' not found");
        }

        $uri = $this->namedRoutes[$name]['uri'];

        // Replace required parameters
        foreach ($this->namedRoutes[$name]['parameters'] as $param => $config) {
            if (!$config['optional'] && !isset($params[$param])) {
                throw new RuntimeException("Required parameter '{$param}' not provided");
            }
            if (isset($params[$param])) {
                $uri = str_replace("{{$param}}", urlencode((string)$params[$param]), $uri);
            }
        }

        // Remove optional parameters that weren't provided
        $uri = preg_replace('/\/{[^}]+\?}/', '', $uri);

        return $uri;
    }
}