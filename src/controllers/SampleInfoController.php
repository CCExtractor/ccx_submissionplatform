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
                // Fetch sample
                $sample = $this->database->getSampleById($args["id"]);
                if($sample !== false){
                    // Fetch media info
                    $media = $this->file_handler->fetchMediaInfo($sample,true);
                    if($media !== false){
                        $this->templateValues->add("sample",$sample);
                        $this->templateValues->add("media",$media);
                        return $this->view->render($response,'sample-info/sample-info-common.html.twig',$this->templateValues->getValues());
                    }
                    $this->templateValues->add("error","error obtaining media info");
                } else {
                    $this->templateValues->add("error","invalid sample id");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_id');
            $this->get('/{hash:[a-z0-9]+}', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                // Fetch sample
                $sample = $this->database->getSampleByHash($args["hash"]);
                if($sample !== false){
                    // Fetch media info
                    $media = $this->file_handler->fetchMediaInfo($sample,true);
                    if($media !== false){
                        $this->templateValues->add("sample",$sample);
                        $this->templateValues->add("media",$media);
                        return $this->view->render($response,'sample-info/sample-info-common.html.twig',$this->templateValues->getValues());
                    }
                    $this->templateValues->add("error","error obtaining media info");
                } else {
                    $this->templateValues->add("error","invalid sample id");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_hash');
        });
    }
}