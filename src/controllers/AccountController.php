<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\AccountManager;
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\objects\NoticeType;
use org\ccextractor\submissionplatform\objects\User;
use org\ccextractor\submissionplatform\objects\UserRole;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class AccountController takes care of handling all account related actions (registration, reset, login, logout, ...).
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class AccountController extends BaseController
{
    /**
     * AccountController constructor.
     */
    public function __construct()
    {
        parent::__construct("My Account", "Manage my account");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app)
    {
        $self = $this;
        $app->group('/account', function () use ($self) {
            // Main page. If not logged in, redirect to login, otherwise to manage.
            /** @var App $this */
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                $url = $this->router->pathFor($self->getPageName() . "_login");
                if ($this->account->isLoggedIn()) {
                    $url = $this->router->pathFor($self->getPageName() . "_manage",
                        ["id" => $this->account->getUser()->getId()]
                    );
                }

                /** @var Response $response */

                return $response->withRedirect($url);
            }
            )->setName($self->getPageName());
            // Login page logic
            $this->group('/login', function () use ($self) {
                /** @var App $this */
                // GET, to show the login page
                $this->get('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $self->setCSRF($this, $request);
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getWarning(),
                        "You are not logged in currently, so you need to login to proceed."
                    );

                    // Render
                    return $this->view->render($response, 'account/login.html.twig', $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_login");
                // POST, to process a login attempt
                $this->post('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    $self->setDefaultBaseValues($this);
                    // Validate login
                    if ($request->getAttribute('csrf_status', true
                        ) === true && isset($_POST["email"]) && isset($_POST["password"])
                    ) {
                        if ($this->account->performLogin($_POST["email"], $_POST["password"])) {
                            return $response->withRedirect($this->router->pathFor("Home"));
                        }
                    }
                    // CSRF values
                    $self->setCSRF($this, $request);
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getError(), "Login failed. Please try again");

                    // Render
                    return $this->view->render($response, 'account/login.html.twig', $this->templateValues->getValues()
                    );
                }
                );
            }
            );
            // Logout page logic
            $this->get('/logout', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                $this->account->performLogout();

                return $response->withRedirect($this->router->pathFor("Home"));
            }
            )->setName($self->getPageName() . "_logout");
            // Recover page logic
            $this->group('/recover', function () use ($self) {
                /** @var App $this */
                // GET: normal procedure for regular user
                $this->get('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $self->setCSRF($this, $request);
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getWarning(),
                        "In order to send you a password reset link, we need the email address linked to your account."
                    );

                    // Render
                    return $this->view->render($response, "account/recover.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_recover");
                // POST: normal procedure for regular user
                $this->post('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    $message = "We could not retrieve an account linked to the given email address, or the CSRF protection is invalid. Please try again";
                    // Fetch user, and send recovery email if it exists
                    /** @var Request $request */
                    if ($request->getAttribute('csrf_status', true) === true && isset($_POST["email"])) {
                        /** @var User $user */
                        $user = $dba->getUserWithEmail($_POST["email"]);
                        if ($user->getId() > -1) {
                            // We found the user, send recovery email and display ok message
                            if ($this->account->sendRecoverEmail($user, $this->view, $request->getUri()->getScheme().'://'.$request->getUri()->getHost())) {
                                // Notice
                                $self->setNoticeValues($this, NoticeType::getSuccess(),
                                    "An email with instructions to reset the password has been sent."
                                );

                                // Render
                                return $this->view->render($response, "account/recover-ok.html.twig",
                                    $this->templateValues->getValues()
                                );
                            } else {
                                $message = "We could not send an email to this account. Please try again later, or get in touch.";
                            }
                        }
                    }
                    // CSRF values
                    $self->setCSRF($this, $request);
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getError(), $message);

                    // Render
                    return $this->view->render($response, "account/recover.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                );
                // GET: recover procedure step 2: choosing a new password
                $this->get('/step2/{id:[0-9]+}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}',
                    function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        $self->setDefaultBaseValues($this);
                        // Check expiration time
                        if (time() <= $args["expires"]) {
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
                                    $self->setCSRF($this, $request);
                                    // Notice
                                    $self->setNoticeValues($this, NoticeType::getWarning(),
                                        "In order to proceed with the password reset, you need to pick a new password and confirm it by entering it a second time."
                                    );

                                    // Render
                                    return $this->view->render($response, "account/recover-password.html.twig",
                                        $this->templateValues->getValues()
                                    );
                                }
                            }
                        }
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getError(),
                            "The token is invalid, or the password has been reset already. Please request a new one."
                        );

                        // Render
                        return $this->view->render($response, "account/invalid-token.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                )->setName($self->getPageName() . "_recover_step2");
                // POST: recover procedure step 2: choosing a new password
                $this->post('/step2/{id:[0-9]+}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}',
                    function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Request $request */
                        $self->setDefaultBaseValues($this);
                        // Check expiration time
                        if (time() <= $args["expires"]) {
                            $user = $this->account->findUser($args["id"]);
                            if ($user !== false) {
                                // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                                $expectedHash = $this->account->getPasswordResetHMAC($user, $args["expires"]);
                                if ($expectedHash === $args["hmac"]) {
                                    // CSRF values
                                    $self->setCSRF($this, $request);
                                    $message = "The given passwords do not match. Please try again.";
                                    // Check if passwords are set and are matching
                                    if ($request->getAttribute('csrf_status', true
                                        ) === true && isset($_POST["password"]) && isset($_POST["password2"]) && $_POST["password"] !== "" && $_POST["password"] === $_POST["password2"]
                                    ) {
                                        if ($this->account->updatePassword($user, $_POST["password"], $this->view)) {
                                            // Notice
                                            $self->setNoticeValues($this, NoticeType::getSuccess(),
                                                "The new password was set! You can now log in with it."
                                            );

                                            // Render
                                            return $this->view->render($response, "account/recover-ok.html.twig",
                                                $this->templateValues->getValues()
                                            );
                                        }
                                        $message = "Failed to update the password! Please try again, or get in touch.";
                                    }
                                    // Variables
                                    $this->templateValues->add("time", $args["expires"]);
                                    $this->templateValues->add("hmac", $args["hmac"]);
                                    $this->templateValues->add("user", $user);
                                    // Notice
                                    $self->setNoticeValues($this, NoticeType::getError(), $message);

                                    // Render
                                    return $this->view->render($response, "account/recover-password.html.twig",
                                        $this->templateValues->getValues()
                                    );
                                }
                            }
                        }
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getError(),
                            "The token is invalid, or the password has been reset already. Please request a new one."
                        );

                        // Render
                        return $this->view->render($response, "account/invalid-token.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                )->setName($self->getPageName() . "_recover_step2_post");
                // GET: admin only, recovery for a specific user
                $this->get('/recover/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        /** @var Response $response */
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // CSRF values
                    $self->setCSRF($this, $request);
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;

                        return $d($request, $response);
                    }
                    $this->templateValues->add("user", $user);

                    return $this->view->render($response, "account/recover-user.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_recover_id");
                // POST: admin only, recovery for a specific user
                $this->post('/recover/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    if ($request->getAttribute('csrf_status') === false) {
                        return $response->withRedirect($this->router->pathFor($self->getPageName() . "_recover_id",
                            ["id" => $args["id"]]
                        )
                        );
                    }
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;

                        return $d($request, $response);
                    }
                    // We found the user, send recovery email and display ok message
                    if ($this->account->sendRecoverEmail($user, $this->view, $request->getUri()->getScheme().'://'.$request->getUri()->getHost())) {
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getSuccess(),
                            "An email with instructions to reset the password has been sent."
                        );

                        // Render
                        return $this->view->render($response, "account/recover-ok.html.twig",
                            $this->templateValues->getValues()
                        );
                    } else {
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getError(),
                            "We could not send an email to this account. Please try again later, or get in touch."
                        );
                    }
                    $this->templateValues->add("user", $user);

                    return $this->view->render($response, "account/recover-user.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                );
            }
            );
            // Register page logic
            $this->group('/register', function () use ($self) {
                /** @var App $this */
                // GET: first page of registering procedure
                $this->get('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $self->setCSRF($this, $request);
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getWarning(),
                        "The registration process is split up in two steps. Please enter your email address first so we can verify it exists."
                    );

                    // Render
                    return $this->view->render($response, "account/registration.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_register");
                // POST: processing the register data
                $this->post('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Request $request */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    $message = "The given email address is invalid.";
                    if ($request->getAttribute('csrf_status', true
                        ) === true && isset($_POST["email"]) && is_email($_POST["email"])
                    ) {
                        // Verify if email is not already existing
                        /** @var User $user */
                        $user = $dba->getUserWithEmail($_POST["email"]);
                        if ($user->getId() === -1) {
                            // Send verification email using a hash
                            if ($this->account->sendRegistrationEmail($_POST["email"], $this->view,
                                $request->getUri()->getScheme().'://'.$request->getUri()->getHost()
                            )
                            ) {
                                // Notice
                                $self->setNoticeValues($this, NoticeType::getSuccess(),
                                    "An email was sent to the given email address. Please follow the instructions to create an account."
                                );

                                // Render
                                return $this->view->render($response, "account/registration-success.html.twig",
                                    $this->templateValues->getValues()
                                );
                            }
                            $message = "Could not send an email. Please try again later, or get in touch.";
                        } else {
                            $message = "There is already a user with this email address.";
                        }
                    }
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getError(), $message);
                    // CSRF values
                    $self->setCSRF($this, $request);

                    // Render
                    return $this->view->render($response, "account/registration.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_register");
                // GET: actual creation and activation of the account
                $this->get('/{email}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}',
                    function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        $self->setDefaultBaseValues($this);
                        // Check expiration time
                        if (time() <= $args["expires"]) {
                            // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                            $expectedHash = $this->account->getRegistrationEmailHMAC($args["email"], $args["expires"]);
                            if ($expectedHash === $args["hmac"]) {
                                // Variables
                                $this->templateValues->add("time", $args["expires"]);
                                $this->templateValues->add("hmac", $args["hmac"]);
                                $this->templateValues->add("email", $args["email"]);
                                // CSRF values
                                $self->setCSRF($this, $request);
                                // Notice
                                $self->setNoticeValues($this, NoticeType::getWarning(),
                                    "To complete the registration we need your name and a password."
                                );

                                // Render
                                return $this->view->render($response, "account/registration-account.html.twig",
                                    $this->templateValues->getValues()
                                );
                            }
                        }
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getError(),
                            "The token is invalid or has expired. Please request a new one."
                        );

                        // Render
                        return $this->view->render($response, "account/invalid-email-token.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                )->setName($self->getPageName() . "_register_activate");
                // POST: processing of the actual creation
                $this->post('/{email}/{expires:[0-9]+}/{hmac:[a-zA-Z0-9]+}',
                    function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Request $request */
                        $self->setDefaultBaseValues($this);
                        // Check expiration time
                        if (time() <= $args["expires"]) {
                            // Check if there's been no tampering (and the password hasn't been changed yet with this token)
                            $expectedHash = $this->account->getRegistrationEmailHMAC($args["email"], $args["expires"]);
                            $message = "One of the values wasn't filled in correctly!";
                            if ($expectedHash === $args["hmac"]) {
                                if ($request->getAttribute('csrf_status', true
                                    ) === true && isset($_POST["name"]) && isset($_POST["password"]) && isset($_POST["password2"]) &&
                                    $_POST["password"] !== "" && $_POST["name"] !== "" && $_POST["password"] === $_POST["password2"]
                                ) {
                                    // Register account
                                    $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
                                    $user = new User(-1, $_POST["name"], $args["email"], $hash);
                                    if ($this->account->registerUser($user, $this->view)) {
                                        // Notice
                                        $self->setNoticeValues($this, NoticeType::getSuccess(),
                                            "The account was created successfully. You are now logged in."
                                        );
                                        $this->templateValues->add("isLoggedIn", $this->account->isLoggedIn());
                                        $this->templateValues->add("loggedInUser", $this->account->getUser());

                                        return $this->view->render($response, "account/registration-success.html.twig",
                                            $this->templateValues->getValues()
                                        );
                                    } else {
                                        $message = "Could not register. Please try again later or get in touch!";
                                    }
                                }
                                // Variables
                                $this->templateValues->add("time", $args["expires"]);
                                $this->templateValues->add("hmac", $args["hmac"]);
                                $this->templateValues->add("email", $args["email"]);
                                // CSRF values
                                $self->setCSRF($this, $request);
                                // Notice
                                $self->setNoticeValues($this, NoticeType::getError(), $message);

                                // Render
                                return $this->view->render($response, "account/registration-account.html.twig",
                                    $this->templateValues->getValues()
                                );
                            }
                        }
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getError(),
                            "The token is invalid or has expired. Please request a new one."
                        );

                        // Render
                        return $this->view->render($response, "account/invalid-email-token.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                );
            }
            );
            // Deactivate page logic
            $this->group('/deactivate/{id:[0-9]+}', function () use ($self) {
                /** @var App $this */
                // GET: verify access and request confirmation
                $this->get('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin() && $this->account->getUser()->getId(
                        ) !== intval($args["id"])
                    ) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;

                        return $d($request, $response);
                    }
                    $this->templateValues->add("user", $user);
                    // CSRF values
                    $self->setCSRF($this, $request);

                    return $this->view->render($response, "account/deactivate-confirm.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_deactivate");
                // POST: process confirmation
                $this->post('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin() && $this->account->getUser()->getId(
                        ) !== intval($args["id"])
                    ) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    /** @var Request $request */
                    if ($request->getAttribute('csrf_status') === false) {
                        // Failed CSRF, redirect to previous page.
                        return $response->withRedirect($this->router->pathFor($self->getPageName() . "_deactivate",
                            ["id" => $args["id"]]
                        )
                        );
                    }
                    /** @var User $user */
                    $user = $this->account->findUser($args["id"]);
                    if ($user === false) {
                        $d = $this->notFoundHandler;

                        return $d($request, $response);
                    }
                    $user->setName("Anonymized");
                    $user->setEmail("ccextractor" . $user->getId() . "@canihavesome.coffee");
                    $user->setHash("");
                    if ($dba->updateUser($user)) {
                        if ($this->account->getUser()->getId() === intval($args["id"])) {
                            // Log out user
                            $this->account->performLogout();
                            $this->templateValues->add("isLoggedIn", $this->account->isLoggedIn());
                            $this->templateValues->add("loggedInUser", $this->account->getUser());
                        }
                        // Notice
                        $self->setNoticeValues($this, NoticeType::getSuccess(),
                            "The account has been deactivated with success."
                        );

                        // Render
                        return $this->view->render($response, "account/deactivate-message.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // Notice
                    $self->setNoticeValues($this, NoticeType::getError(),
                        "The account could not be deactivated. Please get in touch with us, or try again later."
                    );

                    // Render
                    return $this->view->render($response, "account/deactivate-message.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                );
            }
            );
            // User manage page logic
            $this->group('/manage/{id:[0-9]+}', function () use ($self) {
                /** @var App $this */
                // GET: view the edit form for a user.
                $this->get('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if (intval($args["id"]) === $this->account->getUser()->getId()) {
                        $this->templateValues->add("user", $this->account->getUser());
                        // CSRF values
                        $self->setCSRF($this, $request);

                        // Render
                        return $this->view->render($response, "account/manage.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_manage");
                // POST: process form
                $this->post('', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    /** @var User $user */
                    $user = $this->account->getUser();
                    if (intval($args["id"]) === $user->getId()) {
                        $error = true;
                        // Check if the minimum values have been set
                        if ($request->getAttribute('csrf_status', true
                            ) === true && isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["password"])
                        ) {
                            // Verify that we can change (password must be correct)
                            if (password_verify($_POST["password"], $user->getHash())) {
                                // Verify values
                                if ($_POST["name"] !== $user->getName()) {
                                    $user->setName($_POST["name"]);
                                }
                                $oldEmail = null;
                                if (is_email($_POST["email"]) && $_POST["email"] !== $user->getEmail()) {
                                    $oldEmail = $user->setEmail($_POST["email"]);
                                }
                                $password = false;
                                if (isset($_POST["new-password"]) && isset($_POST["new-password2"]) &&
                                    $_POST["new-password"] !== "" && $_POST["new-password"] === $_POST["new-password2"]
                                ) {
                                    // Update password
                                    $user->setHash(password_hash($_POST["new-password"], PASSWORD_DEFAULT));
                                    $password = true;
                                }
                                // Save changes in the database
                                if ($dba->updateUser($user)) {
                                    $this->account->setUser($user);
                                    if ($oldEmail !== null) {
                                        // Send email to old addresses to indicate a change
                                        $message = $this->view->getEnvironment(
                                        )->loadTemplate("email/email_changed.txt.twig"
                                        )->render(["new_email" => $user->getEmail()]);
                                        $this->email->sendEmail($oldEmail, $user->getName(), "Email address changed",
                                            $message
                                        );
                                    }
                                    if ($password) {
                                        // Send email to indicate password change
                                        $message = $this->view->getEnvironment(
                                        )->loadTemplate("email/password_changed.txt.twig")->render([]);
                                        if ($oldEmail !== null) {
                                            $this->email->sendEmail($oldEmail, $user->getName(), "Password changed",
                                                $message
                                            );
                                        }
                                        $this->email->sendEmailToUser($user, "Password changed", $message);
                                    }
                                    $error = false;
                                }
                            }
                        }
                        $this->templateValues->add("user", $this->account->getUser());
                        // CSRF values
                        $self->setCSRF($this, $request);
                        // Notice
                        if ($error) {
                            $self->setNoticeValues($this, NoticeType::getError(),
                                "Some values were not filled in correctly, please try again"
                            );
                        } else {
                            $self->setNoticeValues($this, NoticeType::getSuccess(),
                                "The changes were stored successfully."
                            );
                        }

                        // Render
                        return $this->view->render($response, "account/manage.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                );
            }
            );
            // View user page logic
            $this->group("/view", function () use ($self) {
                /** @var App $this */
                // GET, Show a list of users if admin, or 403 if not.
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if ($this->account->getUser()->isAdmin()) {
                        $this->templateValues->add("users", $dba->listUsers());

                        return $this->view->render($response, "account/userlist.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    /** @var Response $response */

                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_view");
                // GET user, show if admin or own page, 403 otherwise.
                $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if ($this->account->getUser()->isAdmin() || intval($args["id"]) === $this->account->getUser(
                        )->getId()
                    ) {
                        $user = $this->account->findUser($args["id"]);
                        if ($user !== false) {
                            $this->templateValues->add("user", $user);
                            $this->templateValues->add("samples", $dba->getSamplesForUser($user));

                            return $this->view->render($response, "account/user.html.twig",
                                $this->templateValues->getValues()
                            );
                        }
                        $d = $this->notFoundHandler;

                        return $d($request, $response);
                    }

                    /** @var Response $response */

                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_view_id");
            }
            );
            // Manage user rights logic
            $this->map(["GET", "POST"], "/rights/{id:[0-9]+}", function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                /** @var Request $request */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                /** @var AccountManager $account */
                $account = $this->account;
                if (!$account->getUser()->isAdmin()) {
                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                /** @var User $user */
                $user = $account->findUser($args["id"]);
                if ($user === false) {
                    $d = $this->notFoundHandler;

                    return $d($request, $response);
                }
                if ($request->isPost()) {
                    // Anti-CSRF
                    if ($request->getAttribute('csrf_status', true) === true) {
                        if ($_POST["user_role"] !== (int)$user->getRole()) {
                            // Validate new role
                            if (UserRole::isValidValue($_POST["user_role"])) {
                                // Update user
                                $user->setRole(new UserRole($_POST["user_role"]));
                                if ($dba->updateUser($user)) {
                                    // Redirect to list
                                    return $response->withRedirect($this->router->pathFor($self->getPageName() . "_view"
                                    )
                                    );
                                }
                                $self->setNoticeValues($this, NoticeType::getError(), "Failed to update user");
                            } else {
                                $self->setNoticeValues($this, NoticeType::getError(), "Invalid role");
                            }
                        } else {
                            $self->setNoticeValues($this, NoticeType::getWarning(),
                                "Nothing changed, so nothing was stored"
                            );
                        }
                    } else {
                        $self->setNoticeValues($this, NoticeType::getError(), "CSRF values incorrect");
                    }
                }
                $this->templateValues->add("user", $user);
                // CSRF
                $self->setCSRF($this, $request);

                // render
                return $this->view->render($response, "account/rights-confirm.html.twig",
                    $this->templateValues->getValues()
                );
            }
            )->setName($self->getPageName() . "_rights");
        }
        );
    }
}