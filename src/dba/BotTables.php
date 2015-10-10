<?php
namespace org\ccextractor\submissionplatform\dba;

use PDO;
use PDOException;

/**
 * Class BotTables holds all bot related operations on the related database tables.
 *
 * @package org\ccextractor\submissionplatform\dba
 */
class BotTables extends AbstractTables
{
    /**
     * BotTables constructor.
     *
     * @param PDO $pdo The connection to the database system.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }


    /**
     * Fetches all pending entries for the VM queue from the database.
     *
     * @return array A list with all the pending entries.
     */
    public function fetchVMQueue()
    {
        $stmt = $this->pdo->query("SELECT t.id, t.repository, p.`time` FROM test_queue q JOIN test t ON q.test_id = t.id LEFT JOIN test_progress p ON q.`test_id` = p.`test_id` GROUP BY t.id ORDER BY t.`id`, p.`id` ASC;"
        );
        $result = [];
        if ($stmt !== false && $stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    /**
     * Fetches all pending entries for the local queue from the database.
     *
     * @return array A list with all the pending entries.
     */
    public function fetchLocalQueue()
    {
        $stmt = $this->pdo->query("SELECT t.id, t.repository, p.`time` FROM local_queue q JOIN test t ON q.test_id = t.id LEFT JOIN test_progress p ON q.`test_id` = p.`test_id` GROUP BY t.id ORDER BY t.`id`, p.`id` ASC;"
        );
        $result = [];
        if ($stmt !== false && $stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    /**
     * Aborts a queue entry (with the given id) with given message.
     *
     * @param int $id The id of the entry to abort.
     * @param string $abortMessage The message to send to the person who requested this test entry.
     *
     * @return bool True if it succeeded, false otherwise.
     */
    public function abortQueueEntry($id, $abortMessage)
    {
        $id = intval($id);
        $message = str_replace("{0}", $id, $abortMessage);
        if ($this->pdo->beginTransaction()) {
            try {
                $m = $this->pdo->prepare("INSERT INTO github_queue VALUES (NULL, :test_id, :message);");
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->bindParam(":message", $message, PDO::PARAM_STR);
                $m->execute();
                $m = $this->pdo->prepare("UPDATE test SET finished = '1' WHERE id = :test_id");
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->execute();
                $m = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), 'error', 'aborted by admin');"
                );
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->execute();
                $m = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id");
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->execute();
                $this->pdo->commit();

                return true;
                // Bot will automatically turn off the VM in <= 5 minutes
            } catch (PDOException $e) {
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
     *
     * @return bool True if it succeeded, false otherwise.
     */
    public function removeFromQueue($id, $local, $removeMessage)
    {
        $id = intval($id);
        $message = str_replace("{0}", $id, $removeMessage);
        if ($this->pdo->beginTransaction()) {
            try {
                $m = $this->pdo->prepare("INSERT INTO github_queue VALUES (NULL, :test_id, :message);");
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->bindParam(":message", $message, PDO::PARAM_STR);
                $m->execute();
                $m = $this->pdo->prepare("INSERT INTO test_progress VALUES (NULL, :test_id, NOW(), 'error', 'removed by admin');"
                );
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->execute();
                $m = $this->pdo->prepare("UPDATE test SET finished = '1' WHERE id = :test_id");
                $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                $m->execute();
                if ($local) {
                    $m = $this->pdo->prepare("DELETE FROM local_queue WHERE test_id = :test_id");
                    $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                    $m->execute();
                } else {
                    $m = $this->pdo->prepare("DELETE FROM test_queue WHERE test_id = :test_id");
                    $m->bindParam(":test_id", $id, PDO::PARAM_INT);
                    $m->execute();
                }
                $this->pdo->commit();

                return true;
            } catch (PDOException $e) {
                $this->pdo->rollBack();
            }
        }

        return false;
    }

    /**
     * Fetches max. x entries from the command history.
     *
     * @param int $limit The max. amount of entries to fetch.
     *
     * @return array A list of entries.
     */
    public function getCommandHistory($limit = 100)
    {
        $stmt = $this->pdo->query("SELECT * FROM cmd_history ORDER BY id DESC LIMIT " . $limit . ";");
        $result = [];
        if ($stmt !== false && $stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    /**
     * Fetches all trusted users from the database.
     *
     * @return array The list of users that are trusted.
     */
    public function fetchTrustedUsers()
    {
        $stmt = $this->pdo->query("SELECT * FROM trusted_users ORDER BY user ASC");
        $result = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    /**
     * Removes a user from the trusted users table.
     *
     * @param int $id The id of the trusted user to remove.
     *
     * @return bool True on success, false on failure.
     */
    public function removeTrustedUser($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM trusted_users WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Adds a new GitHub user to the list of trusted users.
     *
     * @param string $name The GitHub username of the user to add to the trusted users.
     *
     * @return bool True on success, false on failure.
     */
    public function addTrustedUser($name)
    {
        $stmt = $this->pdo->prepare("INSERT INTO trusted_users VALUES(NULL,:name);");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Fetches all local repositories from the database.
     *
     * @return array A list with all the local repositories.
     */
    public function fetchLocalRepositories()
    {
        $stmt = $this->pdo->query("SELECT * FROM local_repos ORDER BY id ASC;");
        $result = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
        }

        return $result;
    }

    /**
     * Adds a given repository to the local repositories table.
     *
     * @param string $gitHub The github of the repository.
     * @param string $folder The local folder for the worker.
     *
     * @return bool True on success, false otherwise.
     */
    public function addLocalRepository($gitHub, $folder)
    {
        $stmt = $this->pdo->prepare("INSERT INTO local_repos VALUES(NULL,:github,:folder);");
        $stmt->bindParam(":github", $gitHub, PDO::PARAM_STR);
        $stmt->bindParam(":folder", $folder, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Fetches the data on a given local repository.
     *
     * @param int $id The id of the local repository in the table.
     *
     * @return bool|array False on error, array on success.
     */
    public function getLocalRepository($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM local_repos WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = false;
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $result = $stmt->fetch();
        }

        return $result;
    }

    /**
     * Deletes a given repository from the database.
     *
     * @param int $id The id of the repository to delete.
     *
     * @return bool True on success, false otherwise.
     */
    public function removeLocalRepository($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM local_repos WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Checks if the VM has any queued items left.
     *
     * @return bool True if there are items left in the VM queue.
     */
    public function hasQueueItemsLeft()
    {
        $q = $this->pdo->query("SELECT COUNT(*) AS 'left' FROM test_queue");
        if ($q !== false) {
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
    public function hasLocalTokensLeft()
    {
        $q = $this->pdo->query("SELECT t.`token` FROM local_queue l JOIN test t ON l.`test_id` = t.`id` ORDER BY l.`test_id` ASC LIMIT 1;"
        );
        if ($q !== false && $q->rowCount() === 1) {
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
    public function storeGitHubMessage($id, $message)
    {
        $stmt = $this->pdo->prepare("INSERT INTO github_queue VALUES(NULL,:id,:message);");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":message", $message, PDO::PARAM_STR);
        $stmt->execute();
    }
}