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
                $url = $this->router->pathFor($self->getPageName()."_login");
                if($this->account->isLoggedIn()){
                    $url = $this->router->pathFor($self->getPageName()."_manage",["id" => $this->account->getUser()->getId()]);
                }
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
                                $this->templateValues->add("message","An email with instructions to reset the password has been sent.");
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
                $this->get('/step2/{id:[0-9]+}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // Check expiration time
                    if(time() <= $args["expires"]) {
                        $user = $this->account->findUser($args["id"]);
                        if ($user !== false) {
                            // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                            $expectedHash = $this->account->getPasswordResetHMAC($user, $args["expires"]);
                            if ($expectedHash === $args["hmac"]) {
                                // Variables
                                $this->templateValues->add("time", $args["expires"]);
                                $this->templateValues->add("hmac", $args["hmac"]);
                                $this->templateValues->add("user", $user);
                                // CSRF values
                                $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                                $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                                // Message box data
                                $this->templateValues->add("message_type", "warning");
                                $this->templateValues->add("message_icon", "fa-warning");
                                $this->templateValues->add("message", "In order to proceed with the password reset, you need to pick a new password and confirm it by entering it a second time.");

                                return $this->view->render($response,"account/recover-password.html.twig",$this->templateValues->getValues());
                            }
                        }
                    }
                    return $this->view->render($response,"account/invalid-token.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_recover_step2");
                // POST: recover procedure step 2: choosing a new password
                $this->post('/step2/{id:[0-9]+}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // Check expiration time
                    if(time() <= $args["expires"]) {
                        $user = $this->account->findUser($args["id"]);
                        if ($user !== false) {
                            // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                            $expectedHash = $this->account->getPasswordResetHMAC($user, $args["expires"]);
                            if ($expectedHash === $args["hmac"]) {
                                // CSRF values
                                $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                                $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                                // Message box data
                                $this->templateValues->add("message_type", "error");
                                $this->templateValues->add("message_icon", "fa-remove");
                                $this->templateValues->add("message", "The given passwords do not match. Please try again.");
                                // Check if passwords are set and are matching
                                if(isset($_POST["password"]) && isset($_POST["password2"]) && $_POST["password"] !== "" && $_POST["password"] === $_POST["password2"]){
                                    if($this->account->updatePassword($user,$_POST["password"],$this->view)){
                                        $this->templateValues->add("message","The new password was set! You can now log in with it.");
                                        return $this->view->render($response,"account/recover-ok.html.twig",$this->templateValues->getValues());
                                    }
                                    $this->templateValues->add("message", "Failed to update the password! Please try again, or get in touch.");
                                }
                                // Variables
                                $this->templateValues->add("time", $args["expires"]);
                                $this->templateValues->add("hmac", $args["hmac"]);
                                $this->templateValues->add("user", $user);

                                return $this->view->render($response,"account/recover-password.html.twig",$this->templateValues->getValues());
                            }
                        }
                    }
                    return $this->view->render($response,"account/invalid-token.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_recover_step2_post");
                // GET: admin only, recovery for a specific user
                $this->get('/recover/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if(!$this->account->getUser()->isAdmin()){
                        return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                    }
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
                // POST: admin only, recovery for a specific user
                $this->post('/recover/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if(!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    $user = $this->account->findUser($args["id"]);
                    if($user === false){
                        $d = $this->notFoundHandler;
                        return $d($request,$response);
                    }
                    // We found the user, send recovery email and display ok message
                    $base_url = (($this->environment["HTTPS"] === "on")?"https://":"http://").$this->environment["HTTP_HOST"];
                    if($this->account->sendRecoverEmail($user,$this->view,$base_url)){
                        $this->templateValues->add("message","An email with instructions to reset the password has been sent.");
                        return $this->view->render($response,"account/recover-ok.html.twig",$this->templateValues->getValues());
                    } else {
                        $this->templateValues->add("message","We could not send an email to this account. Please try again later, or get in touch.");
                    }
                    $this->templateValues->add("user",$user);
                    return $this->view->render($response,"account/recover-user.html.twig",$this->templateValues->getValues());
                });
            });
            // Register page logic
            $this->group('/register', function () use ($self){
                // GET: first page of registering procedure
                $this->get('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message
                    $this->templateValues->add("type","warning");
                    $this->templateValues->add("type_icon","fa-warning");
                    $this->templateValues->add("message","The registration process is split up in two steps. Please enter your email address first so we can verify it exists.");
                    // Render
                    return $this->view->render($response,"account/registration.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_register");
                // POST: processing the register data
                $this->post('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    $this->templateValues->add("message","The given email address is invalid.");
                    if(isset($_POST["email"]) && is_email($_POST["email"])){
                        // Verify if email is not already existing
                        $user = $this->database->getUserWithEmail($_POST["email"]);
                        if($user->getId() === -1){
                            // Send verification email using a hash
                            $base_url = (($this->environment["HTTPS"] === "on")?"https://":"http://").$this->environment["HTTP_HOST"];
                            if($this->account->sendRegistrationEmail($_POST["email"],$this->view, $base_url)){
                                $this->templateValues->add("message","An email was sent to the given email address. Please follow the instructions to create an account.");
                                return $this->view->render($response,"account/registration-success.html.twig",$this->templateValues->getValues());
                            }
                            $this->templateValues->add("message","Could not send an email. Please try again later, or get in touch.");
                        } else {
                            $this->templateValues->add("message", "There is already a user with this email address.");
                        }
                    }
                    // Message
                    $this->templateValues->add("type","error");
                    $this->templateValues->add("type_icon","fa-remove");
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Render
                    return $this->view->render($response,"account/registration.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_register");
                // GET: actual creation and activation of the account
                $this->get('/{email}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // Check expiration time
                    if(time() <= $args["expires"]) {
                        // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                        $expectedHash = $this->account->getRegistrationEmailHMAC($args["email"], $args["expires"]);
                        if ($expectedHash === $args["hmac"]) {
                            // Variables
                            $this->templateValues->add("time", $args["expires"]);
                            $this->templateValues->add("hmac", $args["hmac"]);
                            $this->templateValues->add("email", $args["email"]);
                            // CSRF values
                            $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                            $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                            // Message
                            $this->templateValues->add("type","warning");
                            $this->templateValues->add("type_icon","fa-warning");
                            $this->templateValues->add("message","To complete the registration we need your name and a password.");
                            // Render
                            return $this->view->render($response,"account/registration-account.html.twig",$this->templateValues->getValues());
                        }
                    }
                    return $this->view->render($response,"account/invalid-email-token.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_register_activate");
                // POST: processing of the actual creation
                $this->post('/{email}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // Check expiration time
                    if(time() <= $args["expires"]) {
                        // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                        $expectedHash = $this->account->getRegistrationEmailHMAC($args["email"], $args["expires"]);
                        if ($expectedHash === $args["hmac"]) {
                            if(isset($_POST["name"]) && isset($_POST["password"]) && isset($_POST["password2"]) &&
                                $_POST["password"] !== "" && $_POST["name"] !== "" && $_POST["password"] === $_POST["password2"]){
                                // Register account
                                $hash = password_hash($_POST["password"],PASSWORD_DEFAULT);
                                $user = new User(-1,$_POST["name"],$args["email"],$hash);
                                if($this->account->registerUser($user,$this->view)){
                                    $this->templateValues->add("message","The account was created successfully. You are now logged in.");
                                    $this->templateValues->add("isLoggedIn", $this->account->isLoggedIn());
                                    $this->templateValues->add("loggedInUser", $this->account->getUser());
                                    return $this->view->render($response,"account/registration-success.html.twig",$this->templateValues->getValues());
                                }
                            }
                            // Return error message
                            // Variables
                            $this->templateValues->add("time", $args["expires"]);
                            $this->templateValues->add("hmac", $args["hmac"]);
                            $this->templateValues->add("email", $args["email"]);
                            // CSRF values
                            $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                            $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                            // Message
                            $this->templateValues->add("type","error");
                            $this->templateValues->add("type_icon","fa-remove");
                            $this->templateValues->add("message","One of the values wasn't filled in correctly!");
                            // Render
                            return $this->view->render($response,"account/registration-account.html.twig",$this->templateValues->getValues());
                        }
                    }
                    return $this->view->render($response,"account/invalid-email-token.html.twig",$this->templateValues->getValues());
                });
            });
            // Deactivate page logic
            $this->group('/deactivate/{id:[0-9]+}', function () use ($self) {
                // GET: verify access and request confirmation
                $this->get('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin() && $this->account->getUser()->getId() !== intval($args["id"])) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;
                        return $d($request, $response);
                    }
                    $this->templateValues->add("user",$user);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    return $this->view->render($response,"account/deactivate-confirm.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName() . "_deactivate");
                // POST: process confirmation
                $this->post('', function($request, $response, $args) use ($self){
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin() && $this->account->getUser()->getId() !== intval($args["id"])) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    /** @var User $user */
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;
                        return $d($request, $response);
                    }
                    $user->setName("Anonymized");
                    $user->setEmail("ccextractor".$user->getId()."@canihavesome.coffee");
                    $user->setHash("");
                    if($this->database->updateUser($user)){
                        if($this->account->getUser()->getId() === intval($args["id"])){
                            // Log out user
                            $this->account->performLogout();
                            $this->templateValues->add("isLoggedIn", $this->account->isLoggedIn());
                            $this->templateValues->add("loggedInUser", $this->account->getUser());
                        }
                        return $this->view->render($response,"account/deactivate-ok.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response,"account/deactivate-fail.html.twig",$this->templateValues->getValues());
                });
            });
            // User manage page logic
            $this->get('/manage/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
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
                            $this->templateValues->add("samples", $this->database->getSamplesForUser($user));
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