<?php
use Slim\App;
use Slim\Views\Twig;

require '../vendor/autoload.php';

// Slim app
$app = new App();

// DI container
$container = $app->getContainer();

$twig_wrapper = new Twig('../src/templates', [
    'cache' => '../twig_cache',
    'strict_variables' => true,
    'autoescape' => true
]);
$twig_wrapper->getEnvironment()->addExtension(new Twig_Extensions_Extension_I18n());

$container->register($twig_wrapper);

// TODO: add global middleware: session, authentication, ...

/*$app->get('/hello/{name}', function ($request, $response, $args) {
    $response->write("Hello, " . $args['name']);
    return $response;
});*/

// Define routes
$app->get('{a:/{0,1}}{b:[home]*}', function($request,$response, $args){
    $response->write("home");
    return $response;
});

// TODO: add routes, parse contents, etc, etc etc.

$app->run();