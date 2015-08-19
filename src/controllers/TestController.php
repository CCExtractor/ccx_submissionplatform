<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\SampleData;
use org\ccextractor\submissionplatform\objects\Test;
use Slim\App;
use Slim\Http\Response;

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
            /** @var App $this */
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                $self->setDefaultBaseValues($this);
                $newest = $this->bot_database->fetchLastXTests();
                $this->templateValues->add("tests",$newest);
                return $this->view->render($response,"test/index.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
            // GET: show test details with a certain id
            $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                /** @var Test $test */
                $test = $this->bot_database->fetchTestInformation($args["id"]);
                if($test->getId() > 0){
                    $this->templateValues->add("test",$test);
                    return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
                }
                return $this->view->render($response->withStatus(404),"test/notfound.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_id");
            // GET: show test details for a ccx version
            $this->get('/ccextractor/{version}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                $commit = $this->database->fetchHashForCCXVersion($args["version"]);
                if($commit !== ""){
                    /** @var Test $test */
                    $test = $this->bot_database->fetchTestInformationForCommit($commit);
                    if($test->getId() > 0){
                        $this->templateValues->add("test",$test);
                        return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
                    } else {

                    }
                }
                return $this->view->render($response->withStatus(404),"test/notfound.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_ccx");
            // GET: show test details for a certain commit
            $this->get('/commit/{hash}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                /** @var Test $test */
                $test = $this->bot_database->fetchTestInformationForCommit($args["hash"]);
                if($test->getId() > 0){
                    $this->templateValues->add("test",$test);
                    return $this->view->render($response,"test/test.html.twig",$this->templateValues->getValues());
                } else {

                }
                return $this->view->render($response->withStatus(404),"test/notfound.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_commit");
            // GET: show test details for a certain sample
            $this->get('/sample/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                $self->setDefaultBaseValues($this);
                /** @var SampleData $sample */
                $sample = $this->database->getSampleById($args["id"]);
                if($sample !== false){
                    $this->templateValues->add("sample",$sample);

                    return $this->view->render($response,"test/sample.html.twig",$this->templateValues->getValues());
                }
            })->setName($self->getPageName()."_sample");
        });
    }
}