# Fastpress Router

The Fastpress Router is a powerful and flexible routing system for PHP applications. It provides a simple and intuitive way to define routes, handle HTTP requests, and manage the flow of your web application.

## Features

- Support for multiple HTTP methods (GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD)
- Route parameters with custom patterns
- Named routes for easy URL generation
- Route groups for shared attributes
- Middleware support
- Route constraints
- Optional parameters
- Conflict detection to prevent ambiguous routes

## Installation

To use the Fastpress Router in your project, you can include it in your PHP files:

```php
use Fastpress\Routing\Router;

require_once 'path/to/Router.php';
```

# Basic Usage
## Defining Routes
```php
$router = new Router();

// Define a simple GET route
$router->get('/hello', function() {
    echo "Hello, World!";
});

// Define a POST route with parameters
$router->post('/users/{id}', 'UserController@update');
```

## Matching Routes
```php
$match = $router->match($_SERVER, $_POST);

if ($match) {
    $handler = $match['handler'];
    $params = $match['params'];
    
    // Execute the handler with the matched parameters
    // ...
} else {
    // No matching route found
    // Handle 404 Not Found
}
```
# Advanced Usage
## Route Groups
```php
$router->group(['prefix' => 'admin', 'middleware' => ['auth']], function($router) {
    $router->get('/dashboard', 'AdminController@dashboard');
    $router->get('/users', 'AdminController@users');
});
```

## Named Routes
```php
$router->get('/profile/{id}', 'ProfileController@show')->name('profile.show');

// Generate URL for named route
$url = $router->getNamedRoute('profile.show', ['id' => 123]);
```

## Custom Route Parameters
```php
$router->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

$router->get('/users/{uuid}', 'UserController@show');
```

## Middleware
```php
$router->get('/admin/dashboard', 'AdminController@dashboard')
    ->middleware('auth');
```

## Route Constraints
```php

$router->get('/users/{id}', 'UserController@show')
    ->where(['id' => '\d+']);
```

# Contributing
Contributions are welcome! Please feel free to submit a Pull Request.
License
This Fastpress Router is open-sourced software licensed under the MIT license.