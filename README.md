# Fastpress Router

Fastpress Router is a simple yet powerful routing class for PHP, part of the Fastpress framework. It allows you to define routes for your application and handle HTTP requests efficiently.

## Features

- Supports GET, POST, PUT, and DELETE HTTP methods.
- Easy definition of route patterns.
- Supports RESTful routing.
- Customizable route patterns.

## Requirements

- PHP 7.0 or higher.

## Installation

Insert instructions for installing this router class or the Fastpress framework.

## Usage

### Basic Usage

```php
require 'path/to/Router.php';

$router = new Fastpress\Routing\Router();

// Define a GET route
$router->get('/home', function() {
    return 'Welcome to the homepage!';
});

// Handle the request
// Assuming $server and $post are your $_SERVER and $_POST variables
$result = $router->match($server, $post);

if ($result) {
    // Route was matched
    list($args, $callable) = $result;
    call_user_func_array($callable, $args);
} else {
    // No route was matched
    header("HTTP/1.0 404 Not Found");
}
```
### Defining Routes
You can define routes for different HTTP methods:
```php
$router->get($uri, $callable);
$router->post($uri, $callable);
$router->put($uri, $callable);
$router->delete($uri, $callable);
```

### Route Patterns
You can use the following placeholders in your route patterns:
- :any - Matches any characters
- :id - Matches numeric characters
- :slug - Matches alphanumeric characters, dashes, and underscores
- :name - Matches alphabetic characters
- :url - Matches alphanumeric characters and URL-friendly symbols

## Contributing
Contributions are welcome! Please feel free to submit a pull request or open issues to improve the library.


## License
This library is open-sourced software licensed under the MIT license.

## Support
If you encounter any issues or have questions, please file them in the issues section on GitHub.
