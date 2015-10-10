<?php
namespace org\ccextractor\submissionplatform\controllers;

use Milo\Github\Http\Response;
use Milo\Github\OAuth\Token;
use Slim\App;

/**
 * Class HomeController handles the actions from the home page of the submission platform.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class HomeController extends BaseController
{
    /**
     * HomeController constructor.
     */
    public function __construct()
    {
        parent::__construct("Home","Homepage of the 2015 GSoC project for CCExtractor for a sample submission platform.");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    public function register(App $app)
    {
        $self = $this;
        // GET: start page of the site/application
        $app->get('/[home]',function($request, $response, $args) use ($self) {
            /** @var App $this */
            $self->setDefaultBaseValues($this);
            // Get latest GitHub commit
            $token = new Token(BOT_TOKEN);
            $this->github->setToken($token);
            $ref = "heads/master";
            $commit = "unknown (error occurred)";
            /** @var Response $request */
            $request = $this->github->get(
                "/repos/:owner/:repo/git/refs/:ref",
                [
                    "owner" => REPOSITORY_OWNER,
                    "repo" => REPOSITORY_NAME,
                    "ref" => $ref
                ]
            );
            if($request->getCode() == Response::S200_OK){
                $json = json_decode($request->getContent());
                if($json !== null && isset($json->ref) && $json->ref == "refs/".$ref){
                    $commit = $json->object->sha;
                }
            }

            // Custom page values

            $this->templateValues->add("ccx_last_release", $this->database->getLatestCCExtractorVersion());
            $this->templateValues->add("ccx_latest_commit", $commit);
            // Render
            return $this->view->render($response,'home/home.html.twig',$this->templateValues->getValues());
        })->setName($this->getPageName());
        // GET: about page
        $app->get("/about",function($request, $response, $args) use ($self) {
            /** @var App $this */
            $self->setDefaultBaseValues($this);
            // Render
            return $this->view->render($response,'home/about.html.twig',$this->templateValues->getValues());
        })->setName($this->getPageName()."_about");
    }
}