<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\controllers;

use Katzgrau\KLogger\Logger;
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use Slim\App;

/**
 * Class GitBotController is an improvement/replacement for the server part of the GitHub bot found at
 * https://github.com/wforums/ccx_gitbot.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class GitBotController extends BaseController
{
    /**
     * @var DatabaseLayer The access to the database.
     */
    private $dba;
    /**
     * @var string The path to the python GitHub bot script.
     */
    private $python_script;
    /**
     * @var string The path to the worker shell script.
     */
    private $worker_script;
    /**
     * @var Logger The instance of the KLogger.
     */
    private $logger;
    /**
     * @var string The GitHub user handle of the bot author.
     */
    private $author;

    /**
     * GitBotController constructor.
     *
     * @param DatabaseLayer $dba
     * @param string $python_script
     * @param string $worker_script
     * @param Logger $logger
     * @param string $author
     */
    public function __construct(DatabaseLayer $dba, $python_script, $worker_script, Logger $logger, $author){
        parent::__construct("GitBot Controller");
        $this->dba = $dba;
        $this->python_script = $python_script;
        $this->worker_script = $worker_script;
        $this->logger = $logger;
        $this->author = $author;
    }

    function register(App $app){
        $self = $this;
        $app->group('/github_bot', function() use ($self){
            $this->post('/report',function($request, $response, $args) use ($self) {
                if($this->environment['HTTP_USER_AGENT'] === CCX_USER_AGENT) {
                    if (isset($_POST["type"]) && isset($_POST["token"])) {
                        $id = $self->dba->bot_validate_token($_POST["token"]);
                        $self->logger->info("Handling request for id ".$id);
                        if ($id > -1) {
                            switch ($_POST["type"]) {
                                case "progress":
                                    // TODO: finish
                                    //$command = $statusHandler->handle_progress($id);
                                    break;
                                case "upload":
                                    // TODO: finish
                                    //$command = $statusHandler->handle_upload($id);
                                    break;
                                default:
                                    $self->logger->warning("Unknown type: ".$_POST["type"]);
                                    break;
                            }
                        }
                    }
                }
                return $response->withStatus(403)->write("INVALID COMMAND");
            })->setName($this->getPageName()."_report");
            $this->post('/fetch',function($request, $response, $args) use ($self) {
                if($this->environment['HTTP_USER_AGENT'] === CCX_USER_AGENT_S) {
                    if (isset($_POST["token"])) {
                        // TODO: finish
                        //$fetch = new FetchHandler(DATABASE_SOURCE_NAME,DATABASE_USERNAME,DATABASE_PASSWORD);
                        //$command = $fetch->handle($_POST["token"]);
                    }
                }
                return $response->withStatus(403)->write("INVALID COMMAND");
            })->setName($this->getPageName()."_fetch");
        });
    }
}