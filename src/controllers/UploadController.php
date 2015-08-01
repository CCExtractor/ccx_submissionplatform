<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\objects\FTPCredentials;
use Slim\App;

class UploadController extends BaseController
{
    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct("Upload","Upload samples to the repository.");
    }

    function register(App $app)
    {
        $self = $this;
        $app->group('/upload', function () use ($self) {
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                if($this->account->isLoggedIn()){
                    // Table rendering
                    $this->templateValues->add("queue",$this->database->getQueuedSamples($this->account->getUser()));
                    $this->templateValues->add("messages",$this->database->getQueuedMessages($this->account->getUser()));
                    // Render
                    return $this->view->render($response,"upload/explain.html.twig",$this->templateValues->getValues());
                }
                return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
            // GET: FTP upload details
            $this->group('/ftp', function () use ($self){
                $this->get('[/]', function ($request, $response, $args) use ($self) {
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
                        return $this->view->render($response,"upload/explain-ftp.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_ftp');
                $this->get('/filezilla', function ($request, $response, $args) use ($self) {
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
            // GET: HTTP upload
            $this->get('/new', function ($request, $response, $args) use ($self) {
                $self->setDefaultBaseValues($this);
                if($this->account->isLoggedIn()){
                    // TODO: finish
                }
                return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName().'_new');
            // Logic for finalizing samples
            $this->group('/process', function () use ($self){
                $this->get('[/]', function ($request, $response, $args) use ($self) {
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
                $this->group('/{id:[0-9]+}', function() use ($self){
                    $this->get('', function ($request, $response, $args) use ($self) {
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($this->account->getUser(), $args["id"]);
                            if($data !== false){
                                // CSRF values
                                $this->templateValues->add("csrf_name", $request->getAttribute('csrf_name'));
                                $this->templateValues->add("csrf_value", $request->getAttribute('csrf_value'));
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
                        $self->setDefaultBaseValues($this);
                        if($this->account->isLoggedIn()){
                            $data = $this->database->getQueuedSample($this->account->getUser(), $args["id"]);
                            if($data !== false){
                                // TODO: finish
                            }
                            return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                        }
                        return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                    });
                });
                $this->get('/link/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        // TODO: finish
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_process_link');
                $this->get('/delete/{id:[0-9]+}', function ($request, $response, $args) use ($self) {
                    $self->setDefaultBaseValues($this);
                    if($this->account->isLoggedIn()){
                        if($this->file_handler->remove($this->account->getUser(),$args["id"])){
                            $url = $this->router->pathFor($self->getPageName()."_process");
                            return $response->withStatus(302)->withHeader('Location',$url);
                        }
                        $this->templateValues->add("error","could not remove sample.");
                        return $this->view->render($response,"upload/process-error.html.twig",$this->templateValues->getValues());
                    }
                    return $this->view->render($response->withStatus(403),"login-required.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName().'_process_delete');
            });
        });
    }
}