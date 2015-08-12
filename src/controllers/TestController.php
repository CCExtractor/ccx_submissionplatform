<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\Test;
use Slim\App;

/**
 * Class TestController holds the logic for displaying test results.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class TestController extends BaseController
{
    /**
     * TestController constructor
     */
    public function __construct(){
        parent::__construct("Test results");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app){
        $self = $this;
        $app->group("/test", function() use ($self) {
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: fetch last 10 test run informations.
                return $this->view->render($response,"test/index.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
            // GET: show test details with a certain id
            $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                /** @var Test $test */
                $test = $this->bot_database->fetchTestInformation($args["id"]);
                if($test->getId() > 0){
                    $this->templateValues->add("test",$test);
                    return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
                }

            })->setName($self->getPageName()."_id");
            // GET: show test details for a ccx version
            $this->get('/ccextractor/{version}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: fetch ccextractor version test info. If unexisting, run tests
                return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_ccx");
            // GET: show test details for a certain commit
            $this->get('/commit/{hash}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: fetch commit test info. If unexisting, run tests
                return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_commit");
            // GET: show test details for a certain sample
            $this->get('/sample/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // TODO: fetch sample overview
                return $this->view->render($response,"test/sample.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_sample");
        });
    }
}