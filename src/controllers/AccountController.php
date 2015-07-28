<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\User;
use Slim\App;

class AccountController extends BaseController
{
    /**
     * AccountController constructor.
     */
    public function __construct()
    {
        parent::__construct("My Account","Manage my account");
    }

    function register(App $app)
    {
        $self = $this;
        $app->group('/account', function () use ($self) {
            // Main page. If not logged in, redirect to login, otherwise to manage.
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                $url = $this->router->pathFor($self->getPageName().($this->account->isLoggedIn()?"_manage":"_login"));
                return $response->withStatus(302)->withHeader('Location',$url);
            })->setName($self->getPageName());
            // Login page logic
            $this->group('/login', function () use ($self) {
                // GET, to show the login page
                $this->get('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message box data
                    $this->templateValues->add("message_type", "warning");
                    $this->templateValues->add("message_icon", "fa-warning");
                    $this->templateValues->add("message", "You are not logged in currently, so you need to login to proceed.");

                    return $this->view->render($response,'account/login.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName()."_login");
                // POST, to process a login attempt
                $this->post('',function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // Validate login
                    if(isset($_POST["email"]) && isset($_POST["password"])){
                        if($this->account->performLogin($_POST["email"],$_POST["password"])){
                            $url = $this->router->pathFor("Home");
                            return $response->withStatus(302)->withHeader('Location',$url);
                        }
                    }
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message box data
                    $this->templateValues->add("message_type", "error");
                    $this->templateValues->add("message_icon", "fa-remove");
                    $this->templateValues->add("message", "Login failed. Please try again");

                    return $this->view->render($response,'account/login.html.twig',$this->templateValues->getValues());
                });
            });
            // Logout page logic
            $this->get('/logout', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                $this->account->performLogout();
                $url = $this->router->pathFor("Home");
                return $response->withStatus(302)->withHeader('Location',$url);
            })->setName($self->getPageName()."_logout");
            // Recover page logic
            $this->group('/recover', function () use ($self) {
                // GET: normal procedure for regular user
                $this->get('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message box data
                    $this->templateValues->add("message_type", "warning");
                    $this->templateValues->add("message_icon", "fa-warning");
                    $this->templateValues->add("message", "In order to send you a password reset link, we need the email address linked to your account.");
                    return $this->view->render($response,"account/recover.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_recover");
                // POST: normal procedure for regular user
                $this->post('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    $this->templateValues->add("message", "We could not retrieve an account linked to the given email address. Please try again");
                    // Fetch user, and send recovery email if it exists
                    if(isset($_POST["email"])){
                        /** @var User $user */
                        $user = $this->database->getUserWithEmail($_POST["email"]);
                        if($user->getId() > -1){
                            // We found the user, send recovery email and display ok message
                            $base_url = (($this->environment["HTTPS"] === "on")?"https://":"http://").$this->environment["HTTP_HOST"];
                            if($this->account->sendRecoverEmail($user,$this->view,$base_url)){
                                return $this->view->render($response,"account/recover-ok.html.twig",$this->templateValues->getValues());
                            } else {
                                $this->templateValues->add("message","We could not send an email to this account. Please try again later, or get in touch.");
                            }
                        }
                    }
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message box data
                    $this->templateValues->add("message_type", "error");
                    $this->templateValues->add("message_icon", "fa-remove");
                    return $this->view->render($response,"account/recover.html.twig",$this->templateValues->getValues());
                });
                // GET: recover procedure step 2: choosing a new password
                $this->get('/step2?userId={id:[0-9]+}&expires={expires:[0-9]+}&hmac={hmac:[a-zA-Z0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // TODO: finish
                    echo "test";
                    return $response->withBody("test");
                })->setName($self->getPageName()."_recover_step2");
                // GET: admin only, recovery for a specific user
                $this->get('/recover/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    if(!$this->account->getUser()->isAdmin()){
                        return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                    }
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    $user = $this->account->findUser($args["id"]);
                    if($user === false){
                        $d = $this->notFoundHandler;
                        return $d($request,$response);
                    }
                    $this->templateValues->add("user",$user);
                    return $this->view->render($response,"account/recover-user.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_recover_id");
            });
            // Register page logic
            $this->get('/register', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle register
            })->setName($self->getPageName()."_register");
            // Deactivate page logic
            $this->get('/deactivate[/{id:[0-9]+}]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle deactivate/anonimisation
            })->setName($self->getPageName()."_deactivate");
            // User manage page logic
            $this->get('/manage', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle manage
                echo "manage";
            })->setName($self->getPageName()."_manage");
            // View user page logic
            $this->group("/view", function () use ($self) {
                // GET, Show a list of users if admin, or 403 if not.
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if($this->account->getUser()->isAdmin()){
                        $this->templateValues->add("users", $this->database->listUsers());
                        return $this->view->render($response,"account/userlist.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_view");
                // GET user, show if admin or own page, 403 otherwise.
                $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);

                    if($this->account->getUser()->isAdmin() || intval($args["id"]) === $this->account->getUser()->getId()){
                        $user = $this->account->findUser($args["id"]);
                        if($user !== false){
                            $this->templateValues->add("user", $user);
                            // TODO: get the sumbitted samples
                            return $this->view->render($response,"account/user.html.twig",$this->templateValues->getValues());
                        }
                        $d = $this->notFoundHandler;
                        return $d($request,$response);
                    }
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_view_id");
            });
        });
    }
}