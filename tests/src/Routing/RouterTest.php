<?php

declare(strict_types=1);

use Fastpress\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testAddingRoutes(): void
    {
        $this->router->get('/users', 'UserController@index');
        $this->router->post('/posts', 'PostController@store');
        $this->router->any('/profile', 'ProfileController@show');

        $routes = $this->router->getRoutes();

        $this->assertCount(2, $routes['GET']);
        $this->assertCount(2, $routes['POST']);
        $this->assertCount(1, $routes['PUT']);
        $this->assertCount(1, $routes['DELETE']);
    }

    public function testSimpleRouteMatching(): void
    {
        $this->router->get('/about', 'PageController@about');

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/about'], []);
        $this->assertNotNull($match);
        $this->assertIsArray($match);
        $this->assertEquals('PageController@about', $match['handler']);

        $noMatch = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/contact'], []);
        $this->assertNull($noMatch);
    }

    public function testRouteMatchingWithParameters(): void
    {
        $this->router->get('/users/{id}', 'UserController@show');

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/123'], []);
        $this->assertNotNull($match);
        $this->assertIsArray($match);
        $this->assertEquals('123', $match['params']['id']);
    }

    public function testRouteMatchingReturnsMiddleware(): void
    {
        $this->router->get('/admin', 'AdminController@index')->middleware('AuthMiddleware');

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin'], []);
        $this->assertNotNull($match);
        $this->assertContains('AuthMiddleware', $match['middleware']);
    }

    public function testRouteGroup(): void
    {
        $this->router->group(['prefix' => '/api'], function (Router $router) {
            $router->get('/users', 'Api\UserController@index');
        });

        $match = $this->router->match(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users'], []);
        $this->assertNotNull($match);
        $this->assertEquals('Api\UserController@index', $match['handler']);
    }

    public function testNoMatchReturnsNull(): void
    {
        $this->router->get('/home', 'HomeController@index');

        $result = $this->router->match(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/home'], []);
        $this->assertNull($result);
    }
}
