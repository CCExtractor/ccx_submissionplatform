<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

class SampleInfoController extends BaseController
{
    /**
     * SampleInfoController constructor.
     */
    public function __construct()
    {
        parent::__construct("Sample Info");
    }

    function register(App $app)
    {
        $self = $this;
        $app->group('/sample-info', function () use ($self) {
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                $this->templateValues->add("samples", $this->database->getAllSamples());
                return $this->view->render($response,'sample-info/sample-info.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName());
            $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                return $this->view->render($response,'sample-info/sample-info-id.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_id');
            $this->get('/{sha1:[a-z0-9]+}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                return $this->view->render($response,'sample-info/sample-info-sha1.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_sha1');
        });
    }
}