<?php
use Katzgrau\KLogger\Logger;
use org\ccextractor\submissionplatform\containers\AccountManager;
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\EmailLayer;
use org\ccextractor\submissionplatform\containers\FileHandler;
use org\ccextractor\submissionplatform\containers\FTPConnector;
use org\ccextractor\submissionplatform\containers\GitWrapper;
use org\ccextractor\submissionplatform\containers\TemplateValues;
use org\ccextractor\submissionplatform\controllers\AccountController;
use org\ccextractor\submissionplatform\controllers\AdminController;
use org\ccextractor\submissionplatform\controllers\GitBotController;
use org\ccextractor\submissionplatform\controllers\HomeController;
use org\ccextractor\submissionplatform\controllers\IController;
use org\ccextractor\submissionplatform\controllers\RegressionController;
use org\ccextractor\submissionplatform\controllers\SampleInfoController;
use org\ccextractor\submissionplatform\controllers\TestController;
use org\ccextractor\submissionplatform\controllers\UploadController;
use Slim\Http\Request;
use Slim\Http\Response;

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include_once '../src/configuration.php';
include_once '../src/bot-configuration.php';
require '../vendor/autoload.php';

// Slim app
$app = new \Slim\App();

$container = $app->getContainer();

// Add CSRF protection middleware
$container['csrf'] = function ($container) {
    $guard = new \Slim\Csrf\Guard();
    $guard->setFailureCallable(function ($request, $response, $next) {
        /** @var Request $request */
        $request = $request->withAttribute("csrf_status", false);
        return $next($request, $response);
    });
    return $guard;
};

$app->add($container->get('csrf'));

// Twig
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('../src/templates', [
        /*'cache' => '../twig_cache',*/
        'strict_variables' => true,
        'autoescape' => true,
        'debug' => true
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new \Slim\Views\TwigExtension($container['router'], $basePath));

    $view->getEnvironment()->addExtension(new Twig_Extensions_Extension_I18n());

    return $view;
};

// Database
$dba = new DatabaseLayer(DATABASE_SOURCE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD);
$container['database'] = $dba;
// Email container
$host = $container->get('environment')["HTTP_HOST"];
$email = new EmailLayer(AMAZON_SES_USER, AMAZON_SES_PASS, $host);
$container["email"] = $email;
// GitHub API
$github = new GitWrapper();
$container['github'] = $github;
// Account Manager
$account = new AccountManager($dba, $email, HMAC_KEY);
$container['account'] = $account;
// Template Values
$templateValues = new TemplateValues();
$container["templateValues"] = $templateValues;
// FTP Connector
$ftp = new FTPConnector($container->get('environment')["HTTP_HOST"], 21, $dba);
$container["FTPConnector"] = $ftp;
// File Handler
$file_handler = new FileHandler($dba, TEMP_STORAGE, PERM_STORAGE, RESULT_STORAGE);
$container["file_handler"] = $file_handler;
// Logger (non added right now)
$logger = new Logger(__DIR__."/../private/logs");

//Override the default Not Found Handler
$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        /** @var Interop\Container\ContainerInterface $container */
        /** @var Response $response */
        /** @var TemplateValues $tv */
        $tv = $container->get('templateValues');
        $tv->add('pageName','404 Not Found');
        $tv->add('pageDescription','404 Not Found');
        $tv->add('isLoggedIn',$container->get('account')->isLoggedIn());
        $tv->add("loggedInUser", $container->get('account')->getUser());

        return $container->get('view')->render($response->withStatus(404),"not-found.html.twig",$tv->getValues());
    };
};

$menuPages = [
    new HomeController(),
    new SampleInfoController(),
    new UploadController(),
    new TestController(),
    new RegressionController(),
    new AccountController()
];

$templateValues->add("pages", $menuPages);

// Define routes

/** @var IController $page */
foreach($menuPages as $page){
    $page->register($app);
}

// These stay out of the pages that will be rendered in the menu, but need to be registered anyway
$nonMenuPages = [
    new GitBotController(
        $dba, BOT_CCX_VBOX_MANAGER, BOT_CCX_WORKER, __DIR__."/reports", $logger, BOT_AUTHOR, BOT_REPOSITORY_FOLDER,
        BOT_HMAC_KEY, BOT_WORKER_URL),
    new AdminController()
];

/** @var IController $page */
foreach($nonMenuPages as $page){
    $page->register($app);
}

$app->run();