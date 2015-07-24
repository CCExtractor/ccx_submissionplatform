<?php
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\GitWrapper;
use org\ccextractor\submissionplatform\controllers\HomeController;
use org\ccextractor\submissionplatform\controllers\IController;
use org\ccextractor\submissionplatform\controllers\SampleInfoController;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../src/configuration.php';
require '../vendor/autoload.php';

// Slim app
$app = new App();

// DI container
$container = $app->getContainer();

// Twig
$view = new Twig('../src/templates', [
    /*'cache' => '../twig_cache',*/
    'strict_variables' => true,
    'autoescape' => true,
    'debug' => true
]);

$view->addExtension(new TwigExtension(
    $container->get('router'),
    $container->get('request')->getUri()
));

$view->getEnvironment()->addExtension(new Twig_Extensions_Extension_I18n());

$container->register($view);

// Database
$dba = new DatabaseLayer(DATABASE_SOURCE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD,[
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
    PDO::ATTR_PERSISTENT => true
]);
$container->register($dba);
// GitHub API
$github = new GitWrapper();
$container->register($github);

$pages = [
    new HomeController(),
    new SampleInfoController(),
    // new UploadController(),
    // new TestSuiteController(),
    // new AccountController()
];

// TODO: add global middleware: session, authentication, ...

$base_values = [];
$base_values["pages"] = $pages;

// Define routes

/** @var IController $page */
foreach($pages as $page){
    $page->register($app,$base_values);
}

$app->run();