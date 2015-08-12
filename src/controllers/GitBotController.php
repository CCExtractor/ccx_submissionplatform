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
     * @var BotDatabaseLayer The access to the database.
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
     * GitBotController constructor.
     *
     * @param BotDatabaseLayer $dba
     * @param string $python_script
     * @param string $worker_script
     * @param Logger $logger
     * @param string $author
     */
    public function __construct(BotDatabaseLayer $dba, $python_script, $worker_script, $reportFolder, Logger $logger, $author){
        parent::__construct("GitBot Controller");
        $this->dba = $dba;
        $this->python_script = $python_script;
        $this->worker_script = $worker_script;
        $this->reportFolder = $reportFolder;
        $this->logger = $logger;
        $this->author = $author;
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app){
        $self = $this;
        $app->group('/github_bot', function() use ($self){
            // POST: reporting status updates from the test-suite/bot
            $this->post('/report',function($request, $response, $args) use ($self) {
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
                                                    return $request->write("OK");
                                                } else {
                                                    return $request->withStatus(403)->write("ERROR");
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
                                                            return $request->withStatus(403)->write("ERROR");
                                                    }
                                                    $self->queue_github_comment($id,$_POST["status"]);
                                                    return $request->write("OK");
                                                } else {
                                                    return $request->withStatus(403)->write("ERROR");
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }
                                    break;
                                case "upload":
                                    if($self->handle_upload($id)){
                                        return $request->write("OK");
                                    } else {
                                        return $request->withStatus(403)->write("ERROR");
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
                if($this->environment['HTTP_USER_AGENT'] === CCX_USER_AGENT_S) {
                    if (isset($_POST["token"])) {
                        return $response->write(json_encode($self->dba->fetchDataForToken($_POST["token"])));
                    }
                }
                return $response->withStatus(403)->write("INVALID COMMAND");
            })->setName($self->getPageName()."_fetch");
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
        $progress = "[status](".BaseController::$BASE_URL."/tests/".$id.")";
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
}