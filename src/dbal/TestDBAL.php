<?php
namespace org\ccextractor\submissionplatform\dbal;

use DateTime;
use org\ccextractor\submissionplatform\objects\Test;
use org\ccextractor\submissionplatform\objects\TestEntry;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class TestDBAL holds all test related operations on the related database tables.
 *
 * @package org\ccextractor\submissionplatform\dbal
 */
class TestDBAL extends AbstractDBAL
{
    /**
     * TestDBAL constructor.
     *
     * @param PDO $pdo The connection to the database system.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Checks if given token is valid.
     *
     * @param string $token The token to validate.
     *
     * @return int The id of the associated test run, or -1 in case of failure.
     */
    public function bot_validate_token($token)
    {
        $prep = $this->pdo->prepare("SELECT id FROM test WHERE token = :token AND finished = 0 LIMIT 1;");
        $prep->bindParam(":token", $token, PDO::PARAM_STR);
        if ($prep->execute() !== false) {
            $data = $prep->fetch();

            return $data['id'];
        }

        return -1;
    }

    /**
     * Fetches an array of data linked to the given token.
     *
     * @param string $token The token we want data for.
     *
     * @return array An array (with fail or success status) containing the data linked to the token.
     */
    public function fetchDataForToken($token)
    {
        $result = ["status" => "failed"];
        $stmt = $this->pdo->prepare("SELECT t.token, t.branch, t.commit_hash, l.local FROM test t JOIN local_repos l ON t.repository = l.github WHERE t.token = :token AND t.`finished` = 0 LIMIT 1;"
        );
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
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
     * Saves a status with a message for a given id.
     *
     * @param int $id The id of the test entry.
     * @param string $status The status of the test entry.
     * @param string $message The message that needs to be stored.
     *
     * @return bool True on success, false on failure.
     */
    public function save_status($id, $status, $message)
    {
        $p = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), :status, :message);");
        $p->bindParam(":test_id", $id, PDO::PARAM_INT);
        $p->bindParam(":status", $status, PDO::PARAM_STR);
        $p->bindParam(":message", $message, PDO::PARAM_STR);

        return $p->execute() !== false && $p->rowCount() === 1;
    }

    /**
     * Marks an entry with a given id as finished.
     *
     * @param int $id The id of the entry that needs to be marked as finished.
     *
     * @return int 0 on failure, 1 for a VM test entry, 2 for a local entry.
     */
    public function mark_finished($id)
    {
        $result = 0;
        if ($this->pdo->beginTransaction()) {
            try {
                $p = $this->pdo->prepare("UPDATE test SET finished = 1 WHERE id = :id");
                $p->bindParam(":id", $id, PDO::PARAM_INT);
                $p->execute();
                $p = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id LIMIT 1");
                $p->bindParam(":test_id", $id, PDO::PARAM_INT);
                $p->execute();
                if ($p->rowCount() !== 1) {
                    // Remove on test_queue failed, so it must be local
                    $p = $this->pdo->prepare("DELETE FROM local_queue WHERE test_id = :test_id LIMIT 1");
                    $p->bindParam(":test_id", $id, PDO::PARAM_INT);
                    $p->execute();
                    if ($p->rowCount() !== 1) {
                        throw new PDOException();
                    }
                    $result = 2;
                } else {
                    $result = 1;
                }
                $this->pdo->commit();
            } catch (PDOException $e) {
                $this->pdo->rollBack();
            }
        }

        return $result;
    }

    /**
     * Fetches test data using a prepared statement.
     *
     * @param PDOStatement $stmt The statement that has been prepared already.
     *
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    private function fetchTestData(PDOStatement $stmt)
    {
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $testEntry = $stmt->fetch();
            $entries = [];
            // Fetch entries
            $stmt = $this->pdo->prepare("SELECT * FROM test_progress WHERE test_id = :id ORDER BY id ASC;");
            $stmt->bindParam(":id", $testEntry["id"], PDO::PARAM_INT);
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $data = $stmt->fetch();
                while ($data !== false) {
                    $entries[] = new TestEntry($data["status"], $data["message"], new DateTime($data["time"]));
                    $data = $stmt->fetch();
                }
            }

            return new Test(
                $testEntry["id"], $testEntry["token"], ($testEntry["finished"] === "1"), $testEntry["repository"],
                $testEntry["branch"], $testEntry["commit_hash"], $testEntry["type"], $entries
            );
        }

        return Test::getNullTest();
    }

    /**
     * Fetches test result information based on the given id.
     *
     * @param int $id The id to fetch the test info for.
     *
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    public function fetchTestInformation($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE id= :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }

    /**
     * Fetches test result information based on the given hash.
     *
     * @param string $hash The hash to fetch the test info for.
     *
     * @return Test A null test in case of failure, or a filled Test with the results.
     */
    public function fetchTestInformationForCommit($hash)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM test WHERE commit_hash = :hash ORDER BY id DESC LIMIT 1;");
        $stmt->bindParam(":hash", $hash, PDO::PARAM_INT);

        return $this->fetchTestData($stmt);
    }

    /**
     * Fetches the last X tests from the database (without progress info).
     *
     * @param int $amount The number of tests to fetch.
     *
     * @return array An array containing the Test objects.
     */
    public function fetchLastXTests($amount = 10)
    {
        $stmt = $this->pdo->query("SELECT * FROM test ORDER BY id DESC LIMIT " . $amount . ";");
        $result = [];
        if ($stmt !== false) {
            $testEntry = $stmt->fetch();
            while ($testEntry !== false) {
                $result[] = new Test(
                    $testEntry["id"], $testEntry["token"], ($testEntry["finished"] === "1"), $testEntry["repository"],
                    $testEntry["branch"], $testEntry["commit_hash"], $testEntry["type"]
                );
                $testEntry = $stmt->fetch();
            }
        }

        return $result;
    }
}