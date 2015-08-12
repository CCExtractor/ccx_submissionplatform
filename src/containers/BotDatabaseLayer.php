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

    public function save_status($id,$status,$message){
        $p = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), :status, :message);");
        $p->bindParam(":test_id",$id,PDO::PARAM_INT);
        $p->bindParam(":status",$status,PDO::PARAM_STR);
        $p->bindParam(":message",$message,PDO::PARAM_STR);
        return $p->execute() !== false && $p->rowCount() === 1;
    }

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

    public function hasQueueItemsLeft(){
        $q = $this->pdo->query("SELECT COUNT(*) AS 'left' FROM test_queue");
        if($q !== false){
            $d = $q->fetch();
            return $d['left'] > 0;
        }
        return false;
    }

    public function hasLocalTokensLeft(){
        $q = $this->pdo->query("SELECT t.`token` FROM local_queue l JOIN test t ON l.`test_id` = t.`id` ORDER BY l.`test_id` ASC LIMIT 1;");
        if($q !== false && $q->rowCount() === 1){
            $d = $q->fetch();
            return $d["token"];
        }
        return false;
    }

    public function store_github_message($id,$message){
        $stmt = $this->pdo->prepare("INSERT INTO github_queue VALUES(NULL,:id,:message);");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        $stmt->bindParam(":message",$message,PDO::PARAM_STR);
        $stmt->execute();
    }

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

    public function fetchTestInformation($id){
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE id= :id LIMIT 1;");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }

    public function fetchTestInformationForCommit($hash){
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE commit_hash = :hash ORDER BY id DESC LIMIT 1;");
        $stmt->bindParam(":hash",$hash,PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }
}