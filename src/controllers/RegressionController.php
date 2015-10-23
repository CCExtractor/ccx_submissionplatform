<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\objects\RegressionTest;
use Slim\App;
use Slim\Http\Response;

/**
 * Class RegressionController holds the logic for regression tests.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class RegressionController extends BaseController
{
    /**
     * TestController constructor
     */
    public function __construct()
    {
        parent::__construct("Regression tests");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app)
    {
        $self = $this;
        $app->group("/regression", function () use ($self) {
            /** @var App $this */
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                $this->templateValues->add("tests",$dba->getRegression()->getAllRegressionTests());
                $this->templateValues->add("categories",$dba->getRegression()->getRegressionCategories());

                return $this->view->render($response, "regression/index.html.twig", $this->templateValues->getValues());
            }
            )->setName($self->getPageName());
            // Group specific regression test
            $this->group('/{id:[0-9]+}', function () use ($self){
                /** @var App $this */
                // Show regression test
                $this->get('[/view]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    // Get specific regression test
                    /** @var RegressionTest $test */
                    $test = $dba->getRegression()->getRegressionTest($args["id"]);
                    if($test->getId() > 0){
                        $this->templateValues->add("test",$test);
                        return $this->view->render($response, "regression/view.html.twig", $this->templateValues->getValues());
                    }
                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/notfound.html.twig",
                        $this->templateValues->getValues()
                    );
                })->setName($self->getPageName()."_id");
                $this->map(['GET','POST'],'/delete', function($request, $response, $args) use ($self){
                    // TODO: finish
                })->setName($self->getPageName()."_id_delete");
                $this->map(['GET','POST'],'/edit', function($request, $response, $args) use ($self){
                    // TODO: finish
                })->setName($self->getPageName()."_id_edit");
                $this->map(['GET','POST'],'/results', function($request, $response, $args) use ($self){
                    // TODO: finish
                })->setName($self->getPageName()."_id_results");
            });
            $this->get('/new', function ($request, $response, $args) use ($self) {
                // TODO: finish
            }
            )->setName($self->getPageName()."_new");
        }
        );
    }
}