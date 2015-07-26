<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

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

    function register(App $app, array $base_values = [])
    {
        $base_values = $this->setDefaultBaseValues($base_values, $app);

        $self = $this;
        $app->group('/account', function () use ($self,$base_values) {
            $this->get('[/]', function ($request, $response, $args) use ($base_values, $self) {
                $url = $this->router->pathFor($self->getPageName().($this->account->isLoggedIn()?"_manage":"_login"));
                return $response->withStatus(302)->withHeader('Location',$url);
            })->setName($self->getPageName());
            $this->group('/login', function () use ($self,$base_values) {
                $this->get('', function ($request, $response, $args) use ($base_values) {
                    // CSRF values
                    $base_values["csrf_name"] = $request->getAttribute('csrf_name');
                    $base_values["csrf_value"] = $request->getAttribute('csrf_value');
                    // Message box data
                    $base_values["message_type"] = "warning";
                    $base_values["message_icon"] = "fa-warning";
                    $base_values["message"] = "You are not logged in currently, so you need to login to proceed.";

                    return $this->view->render($response,'login.html.twig',$base_values);
                })->setName($self->getPageName()."_login");
                $this->post('',function ($request, $response, $args) use ($base_values) {
                    // Validate login
                    var_dump($request->getParsedBody());
                    // CSRF values
                    $base_values["csrf_name"] = $request->getAttribute('csrf_name');
                    $base_values["csrf_value"] = $request->getAttribute('csrf_value');
                    // Message box data
                    $base_values["message_type"] = "error";
                    $base_values["message_icon"] = "fa-remove";
                    $base_values["message"] = "Login failed. Please try again";
                    // TODO: handle login
                    return $this->view->render($response,'login.html.twig',$base_values);
                });
            });

            $this->get('/logout', function ($request, $response, $args) use ($base_values) {
                // TODO: handle logout
            })->setName($self->getPageName()."_logout");
            $this->get('/recover', function ($request, $response, $args) use ($base_values) {
                // TODO: handle recover
            })->setName($self->getPageName()."_recover");
            $this->get('/register', function ($request, $response, $args) use ($base_values) {
                // TODO: handle recover
            })->setName($self->getPageName()."_register");
            $this->get('/manage', function ($request, $response, $args) use ($base_values) {
                // TODO: handle manage
                echo "manage";
            })->setName($self->getPageName()."_manage");
            $this->get('/view/{id:[0-9]+}', function ($request, $response, $args) use ($base_values) {
                // TODO: handle view
            })->setName($self->getPageName()."_view");
            /*$this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($base_values) {
                return $this->view->render($response,'sample-info-id.html.twig',$base_values);
            })->setName($self->getPageName().'_id');*/
        });
    }
}