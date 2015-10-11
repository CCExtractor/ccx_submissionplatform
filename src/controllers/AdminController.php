<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\objects\NoticeType;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class AdminController handles a subset of the admin actions. Others are spread over different controllers.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class AdminController extends BaseController
{
    /**
     * HomeController constructor.
     */
    public function __construct()
    {
        parent::__construct("Admin");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    public function register(App $app)
    {
        $self = $this;
        $app->group("/admin", function() use ($self) {
            /** @var App $this */
            // GET: Display all possible admin tasks
            $this->get('[/]', function($request, $response, $args) use ($self){
                /** @var App $this */
                $self->setDefaultBaseValues($this);
                if($this->account->getUser()->isAdmin()){
                    return $this->view->render($response, "admin/index.html.twig", $this->templateValues->getValues());
                }
                /** @var Response $response */
                return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName());
            $this->map(["GET","POST"],"/blacklist-extensions",function($request, $response, $args) use ($self){
                /** @var App $this */
                /** @var Request $request */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                if($this->account->getUser()->isAdmin()){
                    if($request->isPost()){
                        if($request->getAttribute('csrf_status',true) === true){
                            if(isset($_POST["action"]) && isset($_POST["extension"]) && strlen($_POST["extension"]) > 0){
                                // Process request
                                switch($_POST["action"]){
                                    case "add":
                                        if($dba->addForbiddenExtension($_POST["extension"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"extension ".$_POST["extension"]." added.");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"extension ".$_POST["extension"]." could not be added.");
                                        }
                                        break;
                                    case "delete":
                                        if($dba->deleteForbiddenExtension($_POST["extension"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"extension ".$_POST["extension"]." deleted.");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"extension ".$_POST["extension"]." could not be deleted.");
                                        }
                                        break;
                                    default:
                                        $self->setNoticeValues($this,NoticeType::getError(),"Invalid action.");
                                        break;
                                }
                            } else {
                                $self->setNoticeValues($this,NoticeType::getError(),"Value missing.");
                            }
                        } else {
                            $self->setNoticeValues($this,NoticeType::getError(),"CSRF failed.");
                        }
                    }
                    // Get all extensions
                    $this->templateValues->add("list",$dba->getForbiddenExtensions());
                    // Set CSRF
                    $self->setCSRF($this,$request);
                    // Render
                    return $this->view->render($response, "admin/blacklist_extension.html.twig", $this->templateValues->getValues());
                }
                /** @var Response $response */
                return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
            })->setName($self->getPageName()."_blacklist_extension");
        });
    }
}