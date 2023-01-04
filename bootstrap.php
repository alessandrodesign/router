<?php

use NORTEdev\Router\Router;

require __DIR__ . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

//msgfmt -o pt_br.mo pt_br.po

// create a new Router instance
$router = new Router();
$router->redirect("dsfgdsfsd");
$router->run();
exit;
// add a named route
$router->get('/', function () {
    echo 'Hello World!';
}, 'home');

// add a route with parameter
$router->get('/user/:id', function ($id) {
    echo "User ID: $id";
});

// add a route with multiple parameters
$router->get('/posts/:year/:month', function ($year, $month) {
    echo "Year: $year, Month: $month";
});

// add a route with a middleware
$router->get('/admin', function () {
    echo 'Welcome to the admin area';
})->addMiddleware(function ($route, $method) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: /");
        exit;
    }
});

$router->group('/api', function ($router) {
    $router->get('/users', function () {
        // route path: /api/users
    });
    $router->post('/users', function () {
        // route path: /api/users
    });
}, function ($route, $method) {
    // global middleware
});


// add a namespace per controller
$router->addNamespace('App\Controllers');

// add a route for a controller action
$router->get('/articles', 'ArticlesController@index');

// add a route with parameter for a controller action
$router->get('/articles/:id', 'ArticlesController@show');

// add a route with a middleware for a controller action
$router->get('/profile', 'ProfileController@show')
    ->addMiddleware(function ($route, $method) {
        if (!isset($_SESSION['user'])) {
            header("Location: /login");
            exit;
        }
    });

// group routes with a prefix
$router->group('/api', function ($router) {
    $router->get('/users', function () {
        // route path: /api/users
    });
    $router->post('/users', function () {
        // route path: /api/users
    });
});

// run the router
$router->run();
