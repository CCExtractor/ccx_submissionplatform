<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;
use Slim\Http\Response;

/**
 * Class AdminController handles a subset of the admin actions. Others are spread over different controllers.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class AdminController extends BaseController
{
    /**
     * HomeController constructor.
     */
    public function __construct()
    {
        parent::__construct("Admin");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    public function register(App $app)
    {
        $self = $this;
        $app->group("/admin", function() use ($self) {
            /** @var App $this */
            // GET: Display all possible admin tasks
            $this->get('[/]', function($request, $response, $args) use ($self){
                /** @var App $this */
                $self->setDefaultBaseValues($this);
                if($this->account->getUser()->isAdmin()){
                    return $this->view->render($response, "admin/index.html.twig", $this->templateValues->getValues());
                }
                /** @var Response $response */
                return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
        });
    }
}