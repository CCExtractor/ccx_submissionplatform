<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\Sample;
use Slim\App;
use Slim\Http\Response;

/**
 * Class SampleInfoController handles all actions related to viewing samples and displaying/downloading the related media info.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class SampleInfoController extends BaseController
{
    /**
     * SampleInfoController constructor.
     */
    public function __construct()
    {
        parent::__construct("Sample Info");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app)
    {
        $self = $this;
        $app->group('/sample-info', function () use ($self) {
            /** @var App $this */
            // GET: default; display all samples
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                $self->setDefaultBaseValues($this);
                $this->templateValues->add("samples", $this->database->getAllSamples());
                return $this->view->render($response,'sample-info/sample-info.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName());
            // GET: display a single sample based on id.
            $this->get('/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
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
            // GET: display a single sample based on hash
            $this->get('/{hash:[a-z0-9]+}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
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
                    $this->templateValues->add("error","invalid sample hash");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_hash');
            // GET: offers a download of the media info xml for a given id
            $this->get('/download/media-info/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                // Fetch sample
                /** @var Sample $sample */
                $sample = $this->database->getSampleById($args["id"]);
                if($sample !== false){
                    // Fetch media info
                    $media = $this->file_handler->fetchMediaInfo($sample,true,false);
                    if($media !== false){
                        // Create headers
                        $response = $response->withHeader("Content-type","text/xml");
                        $response = $response->withHeader("Content-Disposition",'attachment; filename="'.$sample->getHash().'.xml"');
                        return $response->write($media);
                    }
                    $this->templateValues->add("error","error obtaining media info");
                } else {
                    $this->templateValues->add("error","invalid sample id");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_media_download');
        });
    }
}