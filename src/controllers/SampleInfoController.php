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

    function register(App $app, array $base_values = [])
    {
        $base_values = $this->setDefaultBaseValues($base_values,$app);

        $self = $this;
        $app->group('/sample-info', function () use ($self,$base_values) {
            $this->get('[/]', function ($request, $response, $args) use ($base_values) {
                $base_values["samples"] = $this->database->getAllSamples();
                return $this->view->render($response,'sample-info.html.twig',$base_values);
            })->setName($self->getPageName());
            $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($base_values) {
                return $this->view->render($response,'sample-info-id.html.twig',$base_values);
            })->setName($self->getPageName().'_id');
            $this->get('/{sha1:[a-z0-9]+}', function ($request, $response, $args) use ($base_values) {
                return $this->view->render($response,'sample-info-sha1.html.twig',$base_values);
            })->setName($self->getPageName().'_sha1');
        });
    }
}