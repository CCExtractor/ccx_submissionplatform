<?php
use org\ccextractor\submissionplatform\containers\AccountManager;
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\EmailLayer;
use org\ccextractor\submissionplatform\containers\FTPConnector;
use org\ccextractor\submissionplatform\containers\GitWrapper;
use org\ccextractor\submissionplatform\containers\TemplateValues;
use org\ccextractor\submissionplatform\controllers\AccountController;
use org\ccextractor\submissionplatform\controllers\HomeController;
use org\ccextractor\submissionplatform\controllers\IController;
use org\ccextractor\submissionplatform\controllers\SampleInfoController;
use org\ccextractor\submissionplatform\controllers\UploadController;
use Slim\App;
use Slim\Container;
use Slim\Csrf\Guard;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // FIXME: replace with session middleware

include_once '../src/configuration.php';
require '../vendor/autoload.php';
// For PHP version < 5.5, need to include the php password fallback. Doesn't override existing one, so can be included without checks.
require '../vendor/ircmaxell/password-compat/lib/password.php';

$container = new Container();

// Slim app
$app = new App($container);

// Add CSRF protection middleware
$app->add(new Guard());

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
// Email container
// FIXME: replace on full launch
$host = "canihavesome.coffee"; //$app->environment["HTTP_HOST"];
$email = new EmailLayer(AMAZON_SES_USER, AMAZON_SES_PASS, $host);
$container->register($email);
// GitHub API
$github = new GitWrapper();
$container->register($github);
// Account Manager
$account = new AccountManager($dba,$email,HMAC_KEY);
$container->register($account);
// Template Values
$templateValues = new TemplateValues();
$container->register($templateValues);
// FTP Connector
$ftp = new FTPConnector($app->environment["HTTP_HOST"],22);
$container->register($ftp);

//Override the default Not Found Handler
$container['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        /** @var TemplateValues $tv */
        $tv = $c->get('templateValues');
        $tv->add('pageName','404 Not Found');
        $tv->add('pageDescription','404 Not Found');
        $tv->add('isLoggedIn',$c->get('account')->isLoggedIn());
        $tv->add("loggedInUser", $c->get('account')->getUser());
        return $c->get('view')->render($response->withStatus(404),"notfound.html.twig",$tv->getValues());
    };
};

$pages = [
    new HomeController(),
    new SampleInfoController(),
    new UploadController(),
    // new TestSuiteController(),
    new AccountController()
];

// FUTURE: add global middleware: session, authentication, ...

$templateValues->add("pages",$pages);

// Define routes

/** @var IController $page */
foreach($pages as $page){
    $page->register($app);
}

$app->run();