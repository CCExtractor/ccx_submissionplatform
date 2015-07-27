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

    function register(App $app)
    {
        $self = $this;
        $app->group('/account', function () use ($self) {
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                $url = $this->router->pathFor($self->getPageName().($this->account->isLoggedIn()?"_manage":"_login"));
                return $response->withStatus(302)->withHeader('Location',$url);
            })->setName($self->getPageName());
            $this->group('/login', function () use ($self) {
                $this->get('', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    // CSRF values
                    $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                    $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
                    // Message box data
                    $this->templateValues->add("message_type", "warning");
                    $this->templateValues->add("message_icon", "fa-warning");
                    $this->templateValues->add("message", "You are not logged in currently, so you need to login to proceed.");

                    return $this->view->render($response,'login.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName()."_login");
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

                    return $this->view->render($response,'login.html.twig',$this->templateValues->getValues());
                });
            });
            $this->get('/logout', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                $this->account->performLogout();
                $url = $this->router->pathFor("Home");
                return $response->withStatus(302)->withHeader('Location',$url);
            })->setName($self->getPageName()."_logout");
            $this->get('/recover', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle recover
            })->setName($self->getPageName()."_recover");
            $this->get('/register', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle register
            })->setName($self->getPageName()."_register");
            $this->get('/manage', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: handle manage
                echo "manage";
            })->setName($self->getPageName()."_manage");
            $this->get('/view[/{id:[0-9]+}]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                if(isset($args["id"])){
                    if($this->account->getUser()->isAdmin() || $args["id"] === $this->account->getUser()->getId()){
                        $user = $this->account->findUser($args["id"]);
                        if($user !== false){
                            $base_values["user"] = $user;
                            return $this->view->render($response,"user.html.twig",$this->templateValues->getValues());
                        }
                        // TODO: improve this?
                        $d = $this->notFoundHandler;
                        return $d($request,$response);
                    }
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                } else {
                    if($this->account->getUser()->isAdmin()){
                        //$base_values["user"] = $this->account->findUser
                        return $this->view->render($response,"userlist.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                }

            })->setName($self->getPageName()."_view");
            /*$this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($base_values) {
                return $this->view->render($response,'sample-info-id.html.twig',$base_values);
            })->setName($self->getPageName().'_id');*/
        });
    }
}