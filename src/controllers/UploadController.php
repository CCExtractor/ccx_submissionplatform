<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\NoticeType;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use SplFileInfo;

/**
 * Class UploadController handles all actions related to ftp/http uploads and sample submission.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class UploadController extends BaseController
{
    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct("Upload","Upload samples to the repository.");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app)
    {
        $self = $this;
        $app->group('/upload', function () use ($self) {
            /** @var App $this */
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                $self->setDefaultBaseValues($this);
                if($this->account->isLoggedIn()){
                    // Table rendering
                    $this->templateValues->add("queue",$this->database->getQueuedSamples($this->account->getUser()));
                    $this->templateValues->add("messages",$this->database->getQueuedMessages($this->account->getUser()));
                    // Admin view
                    if($this->account->getUser()->isAdmin()){
                        $this->templateValues->add("a_queue",$this->database->getQueuedSamples());
                        $this->templateValues->add("a_messages",$this->database->getQueuedMessages());
                    }
                    // Render
                    return $this->view->render($response,"upload/explain.html.twig",$this->templateValues->getValues());
                }
                return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
            // GET: FTP upload details
            $this->group('/ftp', function () use ($self){
                /** @var App $this */
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        $this->templateValues->add("host", $this->FTPConnector->getHost());
                        $this->templateValues->add("port", $this->FTPConnector->getPort());
                        // Fetch FTP username & password for user
                        /** @var FTPCredentials $credentials */
                        $credentials = $this->FTPConnector->getFTPCredentialsForUser($this->account->getUser());
                        if($credentials !== false) {
                            $this->templateValues->add("username", $credentials->getName());
                            $this->templateValues->add("password", $credentials->getPassword());
                        } else {
                            $this->templateValues->add("username", "Error...");
                            $this->templateValues->add("password", "Please get in touch...");
                        }
                        // Render
                        return $this->view->render($response,"upload/explain-ftp.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_ftp');
                $this->get('/filezilla', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        /** @var FTPCredentials $credentials */
                        $credentials = $this->FTPConnector->getFTPCredentialsForUser($this->account->getUser());
                        if($credentials !== false) {
                            $props = [
                                "host" => $this->FTPConnector->getHost(),
                                "port" => $this->FTPConnector->getPort(),
                                "username" => $credentials->getName(),
                                "password" => base64_encode($credentials->getPassword())
                            ];
                            // Create headers
                            $response = $response->withHeader("Content-type","text/xml");
                            $response = $response->withHeader("Content-Disposition",'attachment; filename="FileZilla.xml"');
                            return $response->write($this->view->getEnvironment()->loadTemplate("upload/filezilla-template.xml")->render($props));
                        } else {
                            return $this->view->render($response,"upload/generation-error.html.twig",$this->templateValues->getValues());
                        }
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_ftp_filezilla');
            });
            // HTTP upload upload logic
            $this->group('/new', function() use ($self){
                /** @var App $this */
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        $this->templateValues->add("upload_size", $this->file_handler->file_upload_max_size());
                        // CSRF values
                        $self->setCSRF($this,$request);
                        // Render
                        return $this->view->render($response,"upload/new.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_new');
                $this->post('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        $message = "CSRF token invalid";
                        if($request->getAttribute('csrf_status',true) === true){
                            $message = "No file given";
                            if(isset($_FILES["new_sample"])){
                                // Undefined | Multiple Files | $_FILES Corruption Attack
                                if(isset($_FILES['new_sample']['error']) && !is_array($_FILES['new_sample']['error'])){
                                    switch($_FILES['new_sample']['error']){
                                        case UPLOAD_ERR_OK:
                                            $spl = new SplFileInfo($_FILES['new_sample']['tmp_name']);
                                            // Call file handler
                                            $this->file_handler->process($this->account->getUser(), $spl, $_FILES["new_sample"]["name"]);
                                            // Redirect to process page
                                            return $response->withRedirect($this->router->pathFor($self->getPageName() . "_process"));
                                        case UPLOAD_ERR_NO_FILE:
                                            $message = 'No file sent.';
                                            break;
                                        case UPLOAD_ERR_INI_SIZE:
                                        case UPLOAD_ERR_FORM_SIZE:
                                            $message = 'Exceeded filesize limit.';
                                            break;
                                        default:
                                            $message = 'Unknown errors.';
                                    }
                                }
                            }
                        }
                        $this->templateValues->add("upload_size", $this->file_handler->file_upload_max_size());
                        // Notice
                        $self->setNoticeValues($this,NoticeType::getError(),"Invalid file uploaded! Please try correct this error and try again: ".$message);
                        // CSRF values
                        $self->setCSRF($this,$request);
                        // Render
                        return $this->view->render($response,"upload/new.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                });
            });
            // Logic for finalizing samples
            $this->group('/process', function () use ($self){
                /** @var App $this */
                $this->get('[/]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        // Table rendering
                        $this->templateValues->add("queue",$this->database->getQueuedSamples($this->account->getUser()));
                        $this->templateValues->add("messages",$this->database->getQueuedMessages($this->account->getUser()));
                        // Render
                        return $this->view->render($response,"upload/process.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_process');
                // Logic for finalizing a submission
                $this->group('/{id:[0-9]+}', function() use ($self){
                    /** @var App $this */
                    $this->get('', function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Response $response */
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($args["id"],$this->account->getUser());
                            if($data !== false){
                                // CSRF values
                                $self->setCSRF($this,$request);
                                // Other variables
                                $this->templateValues->add("id", $args["id"]);
                                $this->templateValues->add("ccx_versions", $this->database->getAllCCExtractorVersions());
                                // Render
                                return $this->view->render($response,"upload/finalize.html.twig",$this->templateValues->getValues());
                            }
                            return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                        }
                        return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                    })->setName($self->getPageName().'_process_id');
                    $this->post('', function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Response $response */
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($args["id"],$this->account->getUser());
                            if($data !== false){
                                // Verify posted values
                                if($request->getAttribute('csrf_status',true) === true && isset($_POST["ccx_version"]) && isset($_POST["ccx_os"]) && isset($_POST["ccx_params"]) &&
                                    isset($_POST["notes"]) && strlen($_POST["ccx_params"]) > 0 && strlen($_POST["notes"]) > 0){
                                    $version = $this->database->isCCExtractorVersion($_POST["ccx_version"]);
                                    if($version){
                                        // Store, and redirect
                                        if($this->file_handler->submitSample($this->account->getUser(),$args["id"],$_POST["ccx_version"],$_POST["ccx_os"],$_POST["ccx_params"],$_POST["notes"])){
                                            return $response->withRedirect($this->router->pathFor($self->getPageName()."_process"));
                                        }
                                        $this->templateValues->add("error","could not submit data");
                                        return $this->view->render($response,"upload/process-error.html.twig",$this->templateValues->getValues());
                                    }
                                }
                                // Variables that have been filled in (if defined)
                                if(isset($_POST["notes"])){
                                    $this->templateValues->add("notes",$_POST["notes"]);
                                }
                                if(isset($_POST["ccx_params"])){
                                    $this->templateValues->add("params",$_POST["ccx_params"]);
                                }
                                if(isset($_POST["ccx_version"])){
                                    $this->templateValues->add("version",$_POST["ccx_version"]);
                                }
                                if(isset($_POST["ccx_os"])){
                                    $this->templateValues->add("os",$_POST["ccx_os"]);
                                }
                                // CSRF values
                                $self->setCSRF($this,$request);
                                // Other variables
                                $this->templateValues->add("id", $args["id"]);
                                $this->templateValues->add("ccx_versions", $this->database->getAllCCExtractorVersions());
                                // Notice
                                $self->setNoticeValues($this,NoticeType::getError(),"Some values were not filled in. Please try again.");
                                // Render
                                return $this->view->render($response,"upload/finalize.html.twig",$this->templateValues->getValues());
                            }
                            return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                        }
                        return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                    });
                });
                // Linking logic
                $this->group('/link/{id:[0-9]+}', function() use ($self){
                    /** @var App $this */
                    $this->get('[/]', function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Response $response */
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($args["id"],$this->account->getUser());
                            if($data !== false){
                                // CSRF values
                                $self->setCSRF($this,$request);
                                // Other variables
                                $this->templateValues->add("queued", $data);
                                $this->templateValues->add("samples", $this->database->getSamplesForUser($this->account->getUser()));
                                // Render
                                return $this->view->render($response,"upload/link.html.twig",$this->templateValues->getValues());
                            }
                            return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                        }
                        return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                    })->setName($self->getPageName().'_process_link');
                    $this->post('[/]', function ($request, $response, $args) use ($self) {
                        /** @var App $this */
                        /** @var Response $response */
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($args["id"],$this->account->getUser());
                            if($data !== false){
                                if($request->getAttribute('csrf_status',true) === true && isset($_POST["link_id"])){
                                    $sample = $this->database->getSampleForUser($this->account->getUser(), $_POST["link_id"]);
                                    if($sample !== false){
                                        // Process
                                        if($this->file_handler->appendSample($this->account->getUser(), $args["id"], $_POST["link_id"])){
                                            return $response->withRedirect($this->router->pathFor($self->getPageName()."_process"));
                                        }
                                        $self->setNoticeValues($this,NoticeType::getError(),"Could not save the data");
                                    } else {
                                        $self->setNoticeValues($this,NoticeType::getError(),"Invalid sample id");
                                    }
                                } else {
                                    $self->setNoticeValues($this,NoticeType::getError(),"CSRF failed");
                                }
                                // CSRF values
                                $self->setCSRF($this,$request);
                                // Other variables
                                $this->templateValues->add("queued", $data);
                                $this->templateValues->add("samples", $this->database->getSamplesForUser($this->account->getUser()));
                                // Render
                                return $this->view->render($response,"upload/link.html.twig",$this->templateValues->getValues());
                            }
                            return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                        }
                        return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                    });
                });
                // Delete logic
                $this->get('/delete/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        if($this->account->getUser()->isAdmin() && $this->file_handler->remove($args["id"])){
                            return $response->withRedirect($this->router->pathFor($self->getPageName()));
                        } else if($this->file_handler->remove($args["id"],$this->account->getUser())){
                            return $response->withRedirect($this->router->pathFor($self->getPageName()."_process"));
                        }
                        $this->templateValues->add("error","could not remove sample");
                        return $this->view->render($response,"upload/process-error.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_process_delete');
            });
        });
    }
}