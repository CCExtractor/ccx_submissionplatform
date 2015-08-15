<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use DateTime;
use org\ccextractor\submissionplatform\objects\Test;
use org\ccextractor\submissionplatform\objects\TestEntry;
use PDO;
use PDOException;
use PDOStatement;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class BotDatabaseLayer takes care of the connection to the database.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class BotDatabaseLayer implements ServiceProviderInterface
{
    /**
     * @var PDO The real connection to the database.
     */
    private $pdo;
    /**
     * @var array The default options for the PDO object.
     */
    private $defaultOptions = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_PERSISTENT => true
    ];

    /**
     * Creates a new instance of the DataBaseLayer, which takes care of the connection to the database.
     *
     * @param string $dsn The DSN of the database.
     * @param string $username The user to use for the connection to the database.
     * @param string $password The password of said user.
     * @param array $options Any additional options. If empty, a set of base values will be set.
     */
    public function __construct($dsn, $username, $password, $options = [])
    {
        // Note: array_merge will not work, as the PDO constants are numeric (which results then in a renumbering of
        // the indices of the array).
        foreach ($this->defaultOptions as $key => $value) {
            if (!array_key_exists($key, $options)) {
                $options[$key] = $value;
            }
        }
        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple An Container instance
     */
    public function register(Container $pimple)
    {
        $pimple['bot_database'] = $this;
    }

    /**
     * Checks if given token is valid.
     *
     * @param string $token The token to validate.
     * @return int The id of the associated test run, or -1 in case of failure.
     */
    public function bot_validate_token($token){
        $prep = $this->pdo->prepare("SELECT id FROM test WHERE token = :token AND finished = 0 LIMIT 1;");
        $prep->bindParam(":token", $token, PDO::PARAM_STR);
        if($prep->execute() !== false){
            $data = $prep->fetch();
            return $data['id'];
        }
        return -1;
    }

    /**
     * Saves a status with a message for a given id.
     *
     * @param int $id The id of the test entry.
     * @param string $status The status of the test entry.
     * @param string $message The message that needs to be stored.
     * @return bool True on success, false on failure.
     */
    public function save_status($id,$status,$message){
        $p = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), :status, :message);");
        $p->bindParam(":test_id",$id,PDO::PARAM_INT);
        $p->bindParam(":status",$status,PDO::PARAM_STR);
        $p->bindParam(":message",$message,PDO::PARAM_STR);
        return $p->execute() !== false && $p->rowCount() === 1;
    }

    /**
     * Marks an entry with a given id as finished.
     *
     * @param int $id The id of the entry that needs to be marked as finished.
     * @return int 0 on failure, 1 for a VM test entry, 2 for a local entry.
     */
    public function mark_finished($id)
    {
        $result = 0;
        if($this->pdo->beginTransaction()){
            try {
                $p = $this->pdo->prepare("UPDATE test SET finished = 1 WHERE id = :id");
                $p->bindParam(":id",$id,PDO::PARAM_INT);
                $p->execute();
                $p = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id LIMIT 1");
                $p->bindParam(":test_id", $id, PDO::PARAM_INT);
                $p->execute();
                if($p->rowCount() !== 1) {
                    // Remove on test_queue failed, so it must be local
                    $p = $this->pdo->prepare("DELETE FROM local_queue WHERE test_id = :test_id LIMIT 1");
                    $p->bindParam(":test_id", $id, PDO::PARAM_INT);
                    $p->execute();
                    if($p->rowCount() !== 1){
                        throw new PDOException();
                    }
                    $result =  2;
                } else {
                    $result = 1;
                }
                $this->pdo->commit();
            } catch(PDOException $e){
                $this->pdo->rollBack();
            }
        }
        return $result;
    }

    /**
     * Checks if the VM has any queued items left.
     *
     * @return bool True if there are items left in the VM queue.
     */
    public function hasQueueItemsLeft(){
        $q = $this->pdo->query("SELECT COUNT(*) AS 'left' FROM test_queue");
        if($q !== false){
            $d = $q->fetch();
            return $d['left'] > 0;
        }
        return false;
    }

    /**
     * Checks if the local has any queued items left.
     *
     * @return bool True if there are items left in the local queue.
     */
    public function hasLocalTokensLeft(){
        $q = $this->pdo->query("SELECT t.`token` FROM local_queue l JOIN test t ON l.`test_id` = t.`id` ORDER BY l.`test_id` ASC LIMIT 1;");
        if($q !== false && $q->rowCount() === 1){
            $d = $q->fetch();
            return $d["token"];
        }
        return false;
    }

    /**
     * Stores a message in the GitHub queue for a given id.
     *
     * @param int $id The id of the test entry.
     * @param string $message The message.
     */
    public function store_github_message($id,$message){
        $stmt = $this->pdo->prepare("INSERT INTO github_queue VALUES(NULL,:id,:message);");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        $stmt->bindParam(":message",$message,PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Fetches an array of data linked to the given token.
     *
     * @param string $token The token we want data for.
     * @return array An array (with fail or success status) containing the data linked to the token.
     */
    public function fetchDataForToken($token){
        $result = ["status" => "failed"];

        $stmt = $this->pdo->prepare("SELECT t.token, t.branch, t.commit_hash, l.local FROM test t JOIN local_repos l ON t.repository = l.github WHERE t.token = :token AND t.`finished` = 0 LIMIT 1;");
        $stmt->bindParam(":token",$token,PDO::PARAM_STR);
        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            $result["status"] = "success";
            $result["token"] = $data["token"];
            $result["branch"] = $data["branch"];
            $result["commit"] = $data["commit_hash"];
            $result["git"] = $data["local"];
        }
        return $result;
    }

    /**
     * Fetches test data using a prepared statement.
     *
     * @param PDOStatement $stmt The statement that has been prepared already.
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    private function fetchTestData(PDOStatement $stmt){
        if($stmt->execute() && $stmt->rowCount() === 1){
            $testEntry = $stmt->fetch();
            $entries = [];
            // Fetch entries
            $stmt = $this->pdo->prepare("SELECT * FROM test_progress WHERE test_id = :id ORDER BY id ASC;");
            $stmt->bindParam(":id",$testEntry["id"],PDO::PARAM_INT);
            if($stmt->execute() && $stmt->rowCount() > 0){
                $data = $stmt->fetch();
                while($data !== false){
                    $entries[] = new TestEntry($data["status"],$data["message"],new DateTime($data["time"]));
                    $data = $stmt->fetch();
                }
            }
            return new Test(
                $testEntry["id"],$testEntry["token"],($testEntry["finished"] === "1"),$testEntry["repository"],
                $testEntry["branch"],$testEntry["commit_hash"],$testEntry["type"],$entries
            );
        }
        return Test::getNullTest();
    }

    /**
     * Fetches test result information based on the given id.
     *
     * @param int $id The id to fetch the test info for.
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    public function fetchTestInformation($id){
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE id= :id LIMIT 1;");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }

    /**
     * Fetches test result information based on the given hash.
     *
     * @param string $hash The hash to fetch the test info for.
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    public function fetchTestInformationForCommit($hash){
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE commit_hash = :hash ORDER BY id DESC LIMIT 1;");
        $stmt->bindParam(":hash",$hash,PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }

    /**
     * Fetches the last X tests from the database (without progress info).
     *
     * @param int $amount The number of tests to fetch.
     * @return array An array containing the Test objects.
     */
    public function fetchLastXTests($amount=10){
        $stmt = $this->pdo->query("SELECT * FROM test ORDER BY id DESC LIMIT ".$amount.";");
        $result = [];
        if($stmt !== false){
            $testEntry = $stmt->fetch();
            while($testEntry !== false){
                $result[] = new Test(
                    $testEntry["id"],$testEntry["token"],($testEntry["finished"] === "1"),$testEntry["repository"],
                    $testEntry["branch"],$testEntry["commit_hash"],$testEntry["type"]
                );
                $testEntry = $stmt->fetch();
            }
        }
        return $result;
    }

    /**
     * Fetches all pending entries for the VM queue from the database.
     *
     * @return array A list with all the pending entries.
     */
    public function fetchVMQueue(){
        $stmt = $this->pdo->query("SELECT t.id, t.repository, p.`time` FROM test_queue q JOIN test t ON q.test_id = t.id LEFT JOIN test_progress p ON q.`test_id` = p.`test_id` GROUP BY t.id ORDER BY t.`id`, p.`id` ASC;");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 0){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    /**
     * Fetches all pending entries for the local queue from the database.
     *
     * @return array A list with all the pending entries.
     */
    public function fetchLocalQueue(){
        $stmt = $this->pdo->query("SELECT t.id, t.repository, p.`time` FROM local_queue q JOIN test t ON q.test_id = t.id LEFT JOIN test_progress p ON q.`test_id` = p.`test_id` GROUP BY t.id ORDER BY t.`id`, p.`id` ASC;");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 0){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    /**
     * Aborts a queue entry (with the given id) with given message.
     * @param int $id The id of the entry to abort.
     * @param string $abortMessage The message to send to the person who requested this test entry.
     * @return bool True if it succeeded, false otherwise.
     */
    public function abortQueueEntry($id,$abortMessage){
        $id = intval($id);
        $message = str_replace("{0}",$id,$abortMessage);
        if($this->pdo->beginTransaction()) {
            try {
                $m = $this->pdo->prepare("INSERT INTO github_queue VALUES (NULL, :test_id, :message);");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->bindParam(":message",$message,PDO::PARAM_STR);
                $m->execute();
                $m = $this->pdo->prepare("UPDATE test SET finished = '1' WHERE id = :test_id");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->execute();
                $m = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), 'error', 'aborted by admin');");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->execute();
                $m = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->execute();
                $this->pdo->commit();
                return true;
                // Bot will automatically turn off the VM in <= 5 minutes
            } catch(PDOException $e){
                $this->pdo->rollBack();
            }
        }
        return false;
    }

    /**
     * Removes an item from the (local) queue and inserts a message for the requester.
     *
     * @param int $id The id to remove.
     * @param bool $local Is the test local?
     * @param string $removeMessage The message to send to the requester.
     * @return bool True if it succeeded, false otherwise.
     */
    public function removeFromQueue($id,$local,$removeMessage){
        $id = intval($id);
        $message = str_replace("{0}",$id,$removeMessage);
        if($this->pdo->beginTransaction()) {
            try {
                $m = $this->pdo->prepare("INSERT INTO github_queue VALUES (NULL, :test_id, :message);");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->bindParam(":message",$message,PDO::PARAM_STR);
                $m->execute();
                $m = $this->pdo->prepare("UPDATE test SET finished = '1' WHERE id = :test_id");
                $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                $m->execute();
                if($local){
                    $m = $this->pdo->prepare("DELETE FROM local_queue WHERE test_id = :test_id");
                    $m->bindParam(":test_id",$id,PDO::PARAM_INT);
                    $m->execute();
                } else{
                    $m = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id");
                    $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                    $m->execute();
                }
                $this->pdo->commit();
                return true;
            } catch(PDOException $e){
                $this->pdo->rollBack();
            }
        }
        return false;
    }

    /**
     * Fetches max. x entries from the command history.
     *
     * @param int $limit The max. amount of entries to fetch.
     * @return array A list of entries.
     */
    public function getCommandHistory($limit=100){
        $stmt = $this->pdo->query("SELECT * FROM cmd_history ORDER BY id DESC LIMIT ".$limit.";");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 0){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    /**
     * Fetches all trusted users from the database.
     *
     * @return array The list of users that are trusted.
     */
    public function fetchTrustedUsers(){
        $stmt = $this->pdo->query("SELECT * FROM trusted_users ORDER BY user ASC");
        $result = [];
        if($stmt->execute() && $stmt->rowCount() > 0){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    /**
     * Removes a user from the trusted users table.
     *
     * @param int $id The id of the trusted user to remove.
     * @return bool True on success, false on failure.
     */
    public function removeTrustedUser($id){
        $stmt = $this->pdo->prepare("DELETE FROM trusted_users WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Adds a new GitHub user to the list of trusted users.
     *
     * @param string $name The GitHub username of the user to add to the trusted users.
     * @return bool True on success, false on failure.
     */
    public function addTrustedUser($name){
        $stmt = $this->pdo->prepare("INSERT INTO trusted_users VALUES(NULL,:name);");
        $stmt->bindParam(":name",$name,PDO::PARAM_STR);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Fetches all local repositories from the database.
     *
     * @return array A list with all the local repositories.
     */
    public function fetchLocalRepositories(){
        $stmt = $this->pdo->query("SELECT * FROM local_repos ORDER BY id ASC;");
        $result = [];
        if($stmt->execute() && $stmt->rowCount() > 0){
            $result = $stmt->fetchAll();
        }
        return $result;
    }
}