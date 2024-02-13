<?php

use Fastpress\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testAddingRoutes()
    {
        $this->router->get('/users', function () { /* ... */ });
        $this->router->post('/posts', function () { /* ... */ });
        $this->router->any('/profile', function () { /* ... */ });

        // Assuming each route is stored as an element in the array
        $this->assertCount(2, $this->router->routes['GET']); // 1 from 'get', 1 from 'any'
        $this->assertCount(2, $this->router->routes['POST']); // 1 from 'post', 1 from 'any'
        $this->assertCount(1, $this->router->routes['PUT']); // From 'any'
        $this->assertCount(1, $this->router->routes['DELETE']); // From 'any'
    }

    public function testSimpleRouteMatching()
    {
        $this->router->get('/about', function () { });

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/about'], []);
        $this->assertNotNull($match);
        $this->assertIsArray($match);

        $noMatch = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/contact'], []);
        $this->assertFalse($noMatch);
    }

    public function testRouteMatchingWithParameters()
    {
        $this->router->get('/users/:id', function ($id) { });

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/123'], []);
        $this->assertNotNull($match);
        $this->assertIsArray($match);
        $this->assertEquals(['123'], $match[0]); // Captured parameters
    }

    public function testRestfulMethodDetection()
    {
        $this->router->put('/posts/5', function () { });

        $match = $this->router->match(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/posts/5'], ['_method' => 'PUT']);
        $this->assertNotNull($match);
        $this->assertIsArray($match);
    }

    // ... Additional tests ...
}
