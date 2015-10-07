<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\controllers;

use DOMDocument;
use DOMNode;
use Katzgrau\KLogger\Logger;
use org\ccextractor\submissionplatform\containers\BotDatabaseLayer;
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\objects\NoticeType;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class GitBotController is an improvement/replacement for the server part of the GitHub bot found at
 * https://github.com/canihavesomecoffee/ccx_gitbot.
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
     * @var string The folder that holds the reports.
     */
    private $reportFolder;
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
     * @var string The folder that will hold all the local repository clones.
     */
    private $worker_folder;
    /**
     * @var string The HMAC used for adding/removing repositories from the worker.
     */
    private $hmac;
    /**
     * @var string The location where the worker can be contacted for repository management.
     */
    private $worker_url;

    /**
     * GitBotController constructor.
     *
     * @param DatabaseLayer $dba The access to the database.
     * @param string $python_script The path to the python GitHub bot script.
     * @param string $worker_script The path to the worker shell script.
     * @param string $reportFolder The folder that holds the reports.
     * @param Logger $logger The instance of the KLogger.
     * @param string $author The GitHub user handle of the bot author.
     * @param string $worker_folder The path to the folder that will hold all the local repository clones.
     * @param string $hmac The HMAC used for adding/removing repositories from the worker.
     * @param string $worker_url The location where the worker can be contacted for repository management.
     */
    public function __construct(DatabaseLayer $dba, $python_script, $worker_script, $reportFolder, Logger $logger, $author, $worker_folder, $hmac, $worker_url){
        parent::__construct("GitBot Controller");
        $this->dba = $dba;
        $this->python_script = $python_script;
        $this->worker_script = $worker_script;
        $this->reportFolder = $reportFolder;
        $this->logger = $logger;
        $this->author = $author;
        $this->worker_folder = $worker_folder;
        $this->hmac = $hmac;
        $this->worker_url = $worker_url;
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app){
        $self = $this;
        $app->group('/github_bot', function() use ($self){
            /** @var App $this */
            // POST: reporting status updates from the test-suite/bot
            $this->post('/report',function($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                if($this->environment['HTTP_USER_AGENT'] === BOT_CCX_USER_AGENT) {
                    if (isset($_POST["type"]) && isset($_POST["token"])) {
                        $id = $self->dba->bot_validate_token($_POST["token"]);
                        $self->logger->info("Handling request for id ".$id);
                        if ($id > -1) {
                            switch ($_POST["type"]) {
                                case "progress":
                                    if(isset($_POST["status"]) && isset($_POST["message"])){
                                        switch($_POST["status"]){
                                            case "preparation":
                                            case "running":
                                            case "finalization":
                                                if($self->dba->save_status($id,$_POST["status"], $_POST["message"])){
                                                    return $response->write("OK");
                                                } else {
                                                    return $response->withStatus(403)->write("ERROR");
                                                }
                                            case "finalized":
                                            case "error":
                                                if($self->dba->save_status($id,$_POST["status"], $_POST["message"])){
                                                    $toRelaunch = $self->dba->mark_finished($id);
                                                    switch($toRelaunch){
                                                        case 1:
                                                            // VM queue
                                                            $self->processVMQueue();
                                                            break;
                                                        case 2:
                                                            // Local queue
                                                            $self->processLocalQueue();
                                                            break;
                                                        case 0:
                                                            // Failed to update, fallthrough to fail.
                                                        default:
                                                            return $response->withStatus(403)->write("ERROR");
                                                    }
                                                    $self->queue_github_comment($id,$_POST["status"]);
                                                    return $response->write("OK");
                                                } else {
                                                    return $response->withStatus(403)->write("ERROR");
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }
                                    break;
                                case "upload":
                                    if($self->handle_upload($id)){
                                        return $response->write("OK");
                                    } else {
                                        return $response->withStatus(403)->write("ERROR");
                                    }
                                default:
                                    $self->logger->warning("Unknown type: ".$_POST["type"]);
                                    break;
                            }
                        }
                    }
                }
                return $response->withStatus(403)->write("INVALID COMMAND");
            })->setName($self->getPageName()."_report");
            // POST: fetching the necessary data for a worker.
            $this->post('/fetch',function($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                if($this->environment['HTTP_USER_AGENT'] === BOT_CCX_USER_AGENT_S) {
                    if (isset($_POST["token"])) {
                        return $response->write(json_encode($self->dba->fetchDataForToken($_POST["token"])));
                    }
                }
                return $response->withStatus(403)->write("INVALID COMMAND");
            })->setName($self->getPageName()."_fetch");
            // Admin logic
            $this->group("/admin", function() use ($self) {
                /** @var App $this */
                // GET: / root, shows links to actions below.
                $this->get("[/]",function($request, $response, $args) use ($self){
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    if($this->account->getUser()->isAdmin()){
                        return $this->view->render($response,"github_bot/admin_index.html.twig",$this->templateValues->getValues());
                    }
                    /** @var Response $response */
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_admin");
                // Group the queue logic
                $this->group("/queue", function() use ($self) {
                    /** @var App $this */
                    // GET: vmqueue shows the current vm queue
                    $this->map(['GET', 'POST'],"/vm",function($request, $response, $args) use ($self){
                        /** @var App $this */
                        /** @var Request $request */
                        $self->setDefaultBaseValues($this);
                        if($this->account->getUser()->isAdmin()){
                            // Check if POST's are set
                            if($request->getAttribute('csrf_status',true) === true && isset($_POST["action"]) && isset($_POST["id"])){
                                // Execute action
                                switch($_POST["action"]){
                                    case "abort":
                                        if($this->database->abortQueueEntry($_POST["id"],"The admin aborted your currently running request (id {0}). Please get in touch to know why.")){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"Entry ".$_POST["id"]." was aborted");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"Entry ".$_POST["id"]." could not be aborted");
                                        }
                                        break;
                                    case "remove":
                                        if($this->database->removeFromQueue($_POST["id"],false,"The admin removed your request (id {0}) from the queue. Please get in touch to know why.")){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"Entry ".$_POST["id"]." was removed");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"Entry ".$_POST["id"]." could not be removed");
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                            // Fetch queue
                            $this->templateValues->add("queue",$this->database->fetchVMQueue());
                            // CSRF values
                            $self->setCSRF($this,$request);
                            // Render
                            return $this->view->render($response,"github_bot/queue_vm.html.twig",$this->templateValues->getValues());
                        }
                        /** @var Response $response */
                        return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                    })->setName($self->getPageName()."_admin_queue_vm");
                    // GET: localqueue shows the local queue
                    $this->map(['GET', 'POST'],"/local",function($request, $response, $args) use ($self){
                        /** @var App $this */
                        /** @var Request $request */
                        $self->setDefaultBaseValues($this);
                        if($this->account->getUser()->isAdmin()){
                            // Check if POST's are set
                            if($request->getAttribute('csrf_status',true) === true && isset($_POST["action"]) && isset($_POST["id"])){
                                // Execute action
                                switch($_POST["action"]){
                                    case "remove":
                                        if($this->database->removeFromQueue($_POST["id"],true,"The admin removed your request (id {0}) from the queue. Please get in touch to know why.")){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"Entry ".$_POST["id"]." was removed");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"Entry ".$_POST["id"]." could not be removed");
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                            // Fetch queue
                            $this->templateValues->add("queue",$this->database->fetchLocalQueue());
                            // CSRF values
                            $self->setCSRF($this,$request);
                            // Render
                            return $this->view->render($response,"github_bot/queue_local.html.twig",$this->templateValues->getValues());
                        }
                        /** @var Response $response */
                        return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                    })->setName($self->getPageName()."_admin_queue_local");
                });
                // GET: history shows the history of commands
                $this->get("/history",function($request, $response, $args) use ($self){
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    if($this->account->getUser()->isAdmin()){
                        $this->templateValues->add("queue",$this->database->getCommandHistory());
                        return $this->view->render($response,"github_bot/history.html.twig",$this->templateValues->getValues());
                    }
                    /** @var Response $response */
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_admin_history");
                // GET: manage trusted users
                $this->map(["GET","POST"],"/users", function($request, $response, $args) use ($self){
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    if($this->account->getUser()->isAdmin()){
                        if($request->getAttribute('csrf_status',true) === true && isset($_POST["action"])){
                            switch($_POST["action"]){
                                case "remove":
                                    if(isset($_POST["id"])){
                                        if($this->database->removeTrustedUser($_POST["id"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"User removed");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"User could not be removed");
                                        }
                                    }
                                    break;
                                case "add":
                                    if(isset($_POST["name"])){
                                        if($this->database->addTrustedUser($_POST["name"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"User added");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"User could not be added");
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        // Fetch list of all users
                        $this->templateValues->add("users", $this->database->fetchTrustedUsers());
                        // CSRF values
                        $self->setCSRF($this,$request);
                        // Render
                        return $this->view->render($response,"github_bot/users.html.twig",$this->templateValues->getValues());
                    }
                    /** @var Response $response */
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_admin_users");
                // GET: manage local repositories
                $this->map(["GET","POST"],"/local-repositories", function($request, $response, $args) use ($self){
                    /** @var App $this */
                    $self->setDefaultBaseValues($this);
                    if($this->account->getUser()->isAdmin()){
                        // Process post actions
                        if($request->getAttribute('csrf_status',true) === true && isset($_POST["action"])){
                            switch($_POST["action"]){
                                case "remove":
                                    if(isset($_POST["id"])){
                                        if($self->removeRepository($_POST["id"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"Repository removed");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"Repository could not be removed");
                                        }
                                    }
                                    break;
                                case "add":
                                    if(isset($_POST["name"]) && isset($_POST["folder"])){
                                        if($self->addRepository($_POST["name"],$_POST["folder"])){
                                            $self->setNoticeValues($this,NoticeType::getSuccess(),"Repository added");
                                        } else {
                                            $self->setNoticeValues($this,NoticeType::getError(),"Repository could not be added");
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        // Fetch list of all repositories
                        $this->templateValues->add("repositories", $this->database->fetchLocalRepositories());
                        $this->templateValues->add("worker_folder", $self->worker_folder);
                        // CSRF values
                        $self->setCSRF($this,$request);
                        // Render
                        return $this->view->render($response,"github_bot/repositories.html.twig",$this->templateValues->getValues());
                    }
                    /** @var Response $response */
                    return $this->view->render($response->withStatus(403),"forbidden.html.twig",$this->templateValues->getValues());
                })->setName($self->getPageName()."_admin_local_repos");
            });
        });
    }

    /**
     * Processes the current VM queue in the database (selects one and calls the bot).
     */
    private function processVMQueue(){
        $this->logger->info("Deleted id from VM queue; checking for more");
        // If there's still one or multiple items left in the queue, we'll need to give the python script a
        // kick so it processes the next item.
        $remaining = $this->dba->hasQueueItemsLeft();
        if ($remaining !== false) {
            $this->logger->info("Starting python script");
            // Call python script
            $cmd = "python ".$this->python_script."> ".dirname($this->logger->getLogFilePath())."/python.txt 2>&1 &";
            $this->logger->debug("Shell command: ".$cmd);
            exec($cmd);
            $this->logger->debug("Python script returned");
        }
    }

    /**
     * Processes the current local queue in the database (selects one and calls the worker script).
     */
    private function processLocalQueue(){
        $this->logger->info("Deleted id from local queue; checking for more");
        $token = $this->dba->hasLocalTokensLeft();
        if ($token !== false) {
            $this->logger->info("Starting shell script");
            // Call worker shell script
            $cmd = $this->worker_script." ".escapeshellarg($token)."> ".dirname($this->logger->getLogFilePath())."/shell.txt 2>&1 &";
            $this->logger->debug("Shell command: ".$cmd);
            exec($cmd);
            $this->logger->debug("Shell script returned");
        }
    }

    /**
     * Handles an upload of a file for a test request.
     *
     * @param int $id The id of the test entry.
     * @return bool True if the file was saved, false otherwise.
     */
    private function handle_upload($id){
        // Check if a file was provided
        if(array_key_exists("html",$_FILES)){
            // File data
            $data = $_FILES["html"];
            // Do a couple of basic checks. We expect html
            if($this->endsWith($data["name"],".html") && $data["type"] === "text/html" && $data["error"] === UPLOAD_ERR_OK){
                // Create new folder for id if necessary
                $dir = $this->reportFolder."/".$id."/";
                if(!file_exists($dir)){
                    mkdir($dir);
                }
                // Copy file to the directory
                move_uploaded_file($data["tmp_name"],$dir.basename($data["name"]));
                return true;
            } else {
                // Delete temp file
                @unlink($data["tmp_name"]);
            }
        }
        return false;
    }

    /**
     * Queues a GitHub comment for a given test entry with a certain status.
     *
     * @param int $id The id of the test entry.
     * @param string $status The status of the test entry.
     */
    private function queue_github_comment($id, $status)
    {
        $message = "";
        $progress = "[status](".BaseController::$BASE_URL."/test/".$id.")";
        $reports = "[results](".BaseController::$BASE_URL."/reports/".$id.")";
        switch($status){
            case "finalized":
                // Fetch index.html, parse it and convert to a MD table
                $index = $this->reportFolder."/".$id."/index.html";
                if(file_exists($index)){
                    $dom = new DOMDocument();
                    $dom->loadHTMLFile($index);
                    $tables = $dom->getElementsByTagName("table");
                    if($tables->length > 0){
                        $table = $tables->item(0);
                        // Convert table to markdown
                        $md = "";
                        $errors = false;
                        $firstRow = true;
                        /** @var DOMNode $row */
                        foreach($table->childNodes as $row){
                            if($row->hasChildNodes()){
                                $md .= "|";
                                /** @var DOMNode $cell */
                                foreach($row->childNodes as $cell){
                                    if($cell->nodeType === XML_ELEMENT_NODE) {
                                        $bold = "";
                                        if($cell->hasAttributes()){
                                            $attr = $cell->attributes->getNamedItem("class");
                                            if($attr !== null){
                                                if($attr->nodeValue === "red"){
                                                    $bold="**";
                                                    $errors = true;
                                                }
                                            }
                                        }
                                        $md .= " " . $bold . $cell->textContent . $bold . " |";
                                    }
                                }
                                $md .= "\r\n";
                                if($firstRow){
                                    $md .= str_replace("- -","---",preg_replace('/[^\|\s]/', '-', $md, -1));
                                    $firstRow = false;
                                }
                            }
                        }
                        if($errors){
                            $md .= "It seems that not all tests were passed completely. This is an indication that the output of some files is not as expected (but might be according to you). Please check the ".$reports." page, and verify those files. If you have a question about this report, please contact ".$this->author.".";
                        }
                        $message = "The test suite finished running the test files. Below is a summary of the test results:\r\n\r\n".$md;
                    } else {
                        $message = "The index file contained invalid contents. Please check the ".$progress." page, and get in touch with ".$this->author." in case of an error!";
                    }
                } else {
                    $message = "There is no index file available. Please check the ".$progress." page, and get in touch with ".$this->author." in case of an error!";
                }
                break;
            case "error":
                $message = "An error occurred while running the tests. Please check the ".$progress." page, and correct the error. If you have a question, please contact ".$this->author.".";
                break;
            default:
                break;
        }
        if($message !== ""){
            $this->dba->store_github_message($id,$message);
        }
    }

    /**
     * Borrowed from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
     *
     * @param string $haystack The string to search in.
     * @param string $needle The string to search
     *
     * @return bool True if found, false otherwise.
     */
    private function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }

    /**
     * Adds a new repository to the database, and notifies the worker so it can be initialized.
     *
     * @param string $gitHub The location of the repository on GitHub.
     * @param string $folder The local folder.
     * @return bool True if the git was initialized and added to the database, false otherwise.
     */
    private function addRepository($gitHub, $folder){
        $gitHub = "git://github.com/".$gitHub.".git";
        $folder = $this->worker_folder.$folder;
        if($this->callWorker($gitHub,$folder,"add")){
            return $this->dba->addLocalRepository($gitHub,$folder);
        }
        return false;
    }

    /**
     * Removes a given repository from the database, and notifies the worker so it can be deleted.
     *
     * @param $id
     * @return bool True if the git was removed locally and from the database, false otherwise.
     */
    private function removeRepository($id){
        $data = $this->dba->getLocalRepository($id);
        if($data !== false){
            if($this->callWorker($data["github"],$data["local"],"remove")){
                return $this->dba->removeLocalRepository($id);
            }
        }
        return false;
    }

    /**
     * Makes a CURL call to the worker instance to perform an action.
     *
     * @param string $gitHub The GitHub git url.
     * @param string $folder The local worker folder to store.
     * @param string $action The action to perform for this repository.
     * @return bool True if the call succeeded, false otherwise.
     */
    private function callWorker($gitHub, $folder, $action){
        $data = "github=".urlencode($gitHub)."&folder=".urlencode($folder)."&action=".urlencode($action);
        $hmac = hash_hmac("sha256",$data,$this->hmac);
        // Make POST request to the worker
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->worker_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Need to return the result to the variable
        curl_setopt($ch,CURLOPT_POST,4);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data."&hmac=".urlencode($hmac));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result === "OK";
    }
}