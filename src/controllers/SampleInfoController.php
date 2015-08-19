<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use DateTime;
use org\ccextractor\submissionplatform\objects\CCExtractorVersion;
use org\ccextractor\submissionplatform\objects\NoticeType;
use org\ccextractor\submissionplatform\objects\Sample;
use org\ccextractor\submissionplatform\objects\SampleData;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\SplFileInfo;

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
                /** @var SampleData $sample */
                $sample = $this->database->getSampleById($args["id"]);
                if($sample !== false){
                    // Fetch media info
                    $media = $this->file_handler->fetchMediaInfo($sample,true);
                    if($media !== false){
                        $this->templateValues->add("sample",$sample);
                        $this->templateValues->add("media",$media);
                        if($sample->getNrExtraFiles() > 0){
                            $this->templateValues->add("additional_files", $this->file_handler->fetchAdditionalFiles($sample));
                        }
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
                /** @var SampleData $sample */
                $sample = $this->database->getSampleByHash($args["hash"]);
                if($sample !== false){
                    // Fetch media info
                    $media = $this->file_handler->fetchMediaInfo($sample,true);
                    if($media !== false){
                        $this->templateValues->add("sample",$sample);
                        $this->templateValues->add("media",$media);
                        if($sample->getNrExtraFiles() > 0){
                            $this->templateValues->add("additional_files", $this->file_handler->fetchAdditionalFiles($sample));
                        }
                        return $this->view->render($response,'sample-info/sample-info-common.html.twig',$this->templateValues->getValues());
                    }
                    $this->templateValues->add("error","error obtaining media info");
                } else {
                    $this->templateValues->add("error","invalid sample hash");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_hash');
            // Download logic
            $this->group('/download/{id:[0-9]+}', function() use ($self){
                /** @var App $this */
                // GET: download of small enough files through HTTP
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    // Fetch sample
                    /** @var Sample $sample */
                    $sample = $this->database->getSampleById($args["id"]);
                    if($sample !== false){
                        $response = $response->withHeader("X-Accel-Redirect", "/protected/" . $sample->getSampleFileName());
                        $response = $response->withHeader("Content-type", "application/octet-stream");
                        $response = $response->withHeader("Content-Disposition", 'attachment; filename="' . $sample->getSampleFileName() . '"');
                        return $response;
                    } else {
                        $this->templateValues->add("error","invalid sample id");
                    }
                    return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName().'_download');
                // GET: offers a download of the media info xml for a given id
                $this->get('/media-info', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    // Fetch sample
                    /** @var Sample $sample */
                    $sample = $this->database->getSampleById($args["id"]);
                    if($sample !== false){
                        // Fetch media info
                        $media = $this->file_handler->fetchMediaInfo($sample,true,false);
                        // FUTURE: use nginx pass through instead?
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
                })->setName($self->getPageName().'_download_media');
                // GET: offers a download of an additional file
                $this->get('/additional/{additional:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    // Fetch sample
                    /** @var Sample $sample */
                    $sample = $this->database->getSampleById($args["id"]);
                    if($sample !== false){
                        // Fetch extra file information
                        /** @var SplFileInfo $extra */
                        $extra = $this->file_handler->fetchAdditionalFileName($sample,$args["additional"]);
                        if($extra !== false){
                            $response = $response->withHeader("X-Accel-Redirect", "/protected/extra/" . $extra->getFilename());
                            $response = $response->withHeader("Content-type", "application/octet-stream");
                            $response = $response->withHeader("Content-Disposition", 'attachment; filename="' . $extra->getFilename() . '"');
                            return $response;
                        }
                        $this->templateValues->add("error","invalid index or file not found");
                    } else {
                        $this->templateValues->add("error","invalid sample id");
                    }
                    return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName().'_download_additional');
            });
            // GET/POST: edit sample
            $this->map(["GET","POST"],"/edit/{id:[0-9]+}", function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                /** @var Request $request */
                $self->setDefaultBaseValues($this);
                if (!$this->account->getUser()->isAdmin()) {
                    return $this->view->render($response->withStatus(403), "forbidden.html.twig", $this->templateValues->getValues());
                }
                /** @var SampleData $sample */
                $sample = $this->database->getSampleById($args["id"]);
                if($sample !== false){
                    // Check posts
                    if($request->isPost()){
                        if(isset($_POST["ccx_version"]) && isset($_POST["ccx_os"]) && isset($_POST["ccx_params"]) &&
                            isset($_POST["notes"]) && strlen($_POST["ccx_params"]) > 0 && strlen($_POST["notes"]) > 0
                        ){
                            // Save modified values in the sample
                            $sample->setNotes($_POST["notes"]);
                            $sample->setParameters($_POST["ccx_params"]);
                            $sample->setPlatform($_POST["ccx_os"]);
                            $version = $this->database->isCCExtractorVersion($_POST["ccx_version"]);
                            if($version){
                                $sample->setCcextractorVersion(new CCExtractorVersion($_POST["ccx_version"],"",new DateTime(),""));
                                // Check CSRF
                                if($request->getAttribute('csrf_status', true) === true){
                                    // Save modified values
                                    if($this->database->editSample($sample)){
                                        $self->setNoticeValues($this, NoticeType::getSuccess(), "Changes saved");
                                    } else {
                                        $self->setNoticeValues($this,NoticeType::getError(),"Could not save the changes");
                                    }
                                } else{
                                    $self->setNoticeValues($this, NoticeType::getError(), "CSRF error");
                                }
                            } else {
                                $self->setNoticeValues($this, NoticeType::getError(), "Invalid CCX version");
                            }
                        } else {
                            $self->setNoticeValues($this,NoticeType::getError(),"Not all values were filled in!");
                        }
                    }
                    $this->templateValues->add("sample",$sample);
                    $this->templateValues->add("ccx_versions", $this->database->getAllCCExtractorVersions());
                    // CSRF
                    $self->setCSRF($this,$request);
                    // Render
                    return $this->view->render($response,"sample-info/edit.html.twig",$this->templateValues->getValues());
                } else {
                    $this->templateValues->add("error","invalid sample id");
                }
                return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
            })->setName($self->getPageName().'_edit');
            // Group delete logic
            $this->group("/delete/{id:[0-9]+}", function() use ($self){
                /** @var App $this */
                // GET: delete sample
                $this->map(["GET","POST"],'[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig", $this->templateValues->getValues());
                    }
                    /** @var SampleData $sample */
                    $sample = $this->database->getSampleById($args["id"]);
                    if($sample !== false){
                        if(isset($_POST["submit"])){
                            // Validate CSRF before deleting
                            if($request->getAttribute('csrf_status',true) === true){
                                // Delete sample and related files
                                if($this->file_handler->deleteSample($sample)){
                                    return $response->withRedirect($this->router->pathFor($self->getPageName()));
                                }
                                $self->setNoticeValues($this,NoticeType::getError(),"Could not remove sample");
                            }
                            $self->setNoticeValues($this,NoticeType::getError(),"Invalid CSRF");
                        }
                        // Values
                        $this->templateValues->add("sample",$sample);
                        // CSRF
                        $self->setCSRF($this,$request);
                        // Render
                        return $this->view->render($response,"sample-info/delete.html.twig",$this->templateValues->getValues());
                    } else {
                        $this->templateValues->add("error","invalid sample id");
                    }
                    return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName().'_delete');
                // GET: delete additional file
                $this->map(["GET","POST"],'/additional/{additional:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig", $this->templateValues->getValues());
                    }
                    /** @var SampleData $sample */
                    $sample = $this->database->getSampleById($args["id"]);
                    if($sample !== false){
                        // Fetch extra file information
                        /** @var SplFileInfo $extra */
                        $extra = $this->file_handler->fetchAdditionalFileName($sample,$args["additional"]);
                        if($extra !== false){
                            if(isset($_POST["submit"])){
                                // Validate CSRF before deleting
                                if($request->getAttribute('csrf_status',true) === true){
                                    // Delete sample and related files
                                    if($this->file_handler->deleteAdditionalFile($sample,$extra)){
                                        return $response->withRedirect($this->router->pathFor($self->getPageName()));
                                    }
                                    $self->setNoticeValues($this,NoticeType::getError(),"Could not remove additional file");
                                }
                                $self->setNoticeValues($this,NoticeType::getError(),"Invalid CSRF");
                            }
                            // Values
                            $this->templateValues->add("sample",$sample);
                            $this->templateValues->add("additional",$args["additional"]);
                            $this->templateValues->add("extra",$extra);
                            // CSRF
                            $self->setCSRF($this,$request);
                            // Render
                            return $this->view->render($response,"sample-info/delete-additional.html.twig",$this->templateValues->getValues());
                        }
                        $this->templateValues->add("error","invalid additional index");

                    } else {
                        $this->templateValues->add("error","invalid sample id");
                    }
                    return $this->view->render($response,'sample-info/sample-info-error.html.twig',$this->templateValues->getValues());
                })->setName($self->getPageName().'_delete_additional');
            });
        });
    }
}