<?php
namespace org\ccextractor\submissionplatform\containers;

use DateTime;
use org\ccextractor\submissionplatform\objects\AdditionalFile;
use org\ccextractor\submissionplatform\objects\CCExtractorVersion;
use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\QueuedSample;
use org\ccextractor\submissionplatform\objects\Sample;
use org\ccextractor\submissionplatform\objects\SampleData;
use org\ccextractor\submissionplatform\objects\Test;
use org\ccextractor\submissionplatform\objects\TestEntry;
use org\ccextractor\submissionplatform\objects\User;
use PDO;
use PDOException;
use PDOStatement;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class DatabaseLayer takes care of the connection to the database.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class DatabaseLayer implements ServiceProviderInterface
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
        $pimple['database'] = $this;
    }

    /**
     * Gets the latest version of CCExtractor.
     *
     * @return CCExtractorVersion The latest CCExtractor version, or a null object in case of an error.
     */
    public function getLatestCCExtractorVersion()
    {
        $stmt = $this->pdo->query("SELECT * FROM ccextractor_versions ORDER BY ID DESC LIMIT 1;");
        if ($stmt !== false) {
            $data = $stmt->fetch();
            $result = new CCExtractorVersion($data["id"], $data["version"], new DateTime($data["released"]),
                $data["hash"]
            );
        } else {
            $result = CCExtractorVersion::getNullObject();
        }

        return $result;
    }

    /**
     * Gets a list of all CCExtractor versions.
     *
     * @return array An array of CCX versions.
     */
    public function getAllCCExtractorVersions()
    {
        $stmt = $this->pdo->query("SELECT * FROM ccextractor_versions ORDER BY id DESC;");
        $result = [];
        if ($stmt !== false && $stmt->rowCount() > 1) {
            $data = $stmt->fetch();
            while ($data !== false) {
                $result[] = new CCExtractorVersion($data["id"], $data["version"], new DateTime($data["released"]),
                    $data["hash"]
                );
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Checks if a CCExtractor version exists with given id.
     *
     * @param int $id The id of the CCExtractor version to check.
     *
     * @return bool True if there exists a version with given id.
     */
    public function isCCExtractorVersion($id)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ccextractor_versions WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return ($stmt->execute() !== false && $stmt->rowCount() == 1);
    }

    /**
     * Gets all samples, sorted by extension.
     *
     * @return array The samples.
     */
    public function getAllSamples()
    {
        $p = $this->pdo->prepare("SELECT * FROM sample ORDER BY extension ASC");
        $result = [];
        if ($p->execute()) {
            $data = $p->fetch();
            while ($data !== false) {
                $result[] = new Sample($data["id"], $data["hash"], $data["extension"], $data["original_name"]);
                $data = $p->fetch();
            }
        }

        return $result;
    }

    /**
     * Gets a user that has the given email address.
     *
     * @param string $email The email address we want a user for.
     *
     * @return User A user object if found, or the null user.
     */
    public function getUserWithEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE email = :email LIMIT 1");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $data = $stmt->fetch();

            return new User($data["id"], $data["name"], $data["email"], $data["password"], $data["github_linked"],
                $data["admin"]
            );
        }

        return User::getNullUser();
    }

    /**
     * Returns a user with a given id, or the null user if not found.
     *
     * @param int $id The id of the user.
     *
     * @return User The user object.
     */
    public function getUserWithId($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $data = $stmt->fetch();

            return new User($data["id"], $data["name"], $data["email"], $data["password"], $data["github_linked"],
                $data["admin"]
            );
        }

        return User::getNullUser();
    }

    /**
     * Gets a list of all registered users.
     *
     * @return array A list of all registered users.
     */
    public function listUsers()
    {
        $stmt = $this->pdo->query("SELECT * FROM user ORDER BY id ASC");
        $result = [];
        if ($stmt !== false && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            while ($data !== false) {
                $result[] = new User($data["id"], $data["name"], $data["email"], $data["password"],
                    $data["github_linked"], $data["admin"]
                );
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Stores a modified user object in the database.
     *
     * @param User $user The user object that's modified compared to the database entry.
     *
     * @return bool True if the update worked, false otherwise.
     */
    public function updateUser(User $user)
    {
        $name = $user->getName();
        $email = $user->getEmail();
        $hash = $user->getHash();
        $github = $user->isGithub();
        $admin = $user->isAdmin();
        $id = $user->getId();
        $stmt = $this->pdo->prepare("UPDATE user SET name = :name, email = :email, password = :password, github_linked = :github, admin = :admin WHERE id = :id"
        );
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password", $hash, PDO::PARAM_STR);
        $stmt->bindParam(":github", $github, PDO::PARAM_BOOL);
        $stmt->bindParam(":admin", $admin, PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return $stmt->rowCount() === 1;
        }

        return false;
    }

    /**
     * Gets all samples for a given user.
     *
     * @param User $user The user we want to see the samples of.
     *
     * @return array A list of the submitted samples.
     */
    public function getSamplesForUser(User $user)
    {
        $id = $user->getId();
        $p = $this->pdo->prepare("SELECT s.* FROM sample s JOIN upload u ON s.id = u.sample_id WHERE u.user_id = :id ORDER BY s.id ASC;"
        );
        $p->bindParam(":id", $id, PDO::PARAM_INT);
        $result = [];
        if ($p->execute()) {
            $data = $p->fetch();
            while ($data !== false) {
                $result[] = new Sample($data["id"], $data["hash"], $data["extension"], $data["original_name"]);
                $data = $p->fetch();
            }
        }

        return $result;
    }

    /**
     * Gets a specific sample for a given user.
     *
     * @param User $user The user we want to get a sample for.
     * @param int $id The sample id.
     *
     * @return bool|SampleData False on failure or the SampleData on success.
     */
    public function getSampleForUser(User $user, $id)
    {
        $uid = $user->getId();
        $p = $this->pdo->prepare("
SELECT s.*, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.notes, u.parameters, u.platform
FROM sample s
	JOIN upload u ON s.id = u.sample_id
	JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE u.user_id = :uid AND s.id = :id LIMIT 1;"
        );
        $p->bindParam(":uid", $uid, PDO::PARAM_INT);
        $p->bindParam(":id", $id, PDO::PARAM_INT);
        $result = false;
        if ($p->execute()) {
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"], $user,
                new CCExtractorVersion($data["ccx_id"], $data["version"], new DateTime($data["released"]),
                    $data["ccx_hash"]
                ),
                $data["platform"], $data["parameters"], $data["notes"]
            );
        }

        return $result;
    }

    /**
     * Gets a sample for the given id.
     *
     * @param int $id The sample to get.
     *
     * @return bool|SampleData False on failure, sample data otherwise.
     */
    public function getSampleById($id)
    {
        $p = $this->pdo->prepare("
SELECT s.*, uu.id AS 'user_id', uu.name AS 'user_name', uu.email, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.notes, u.parameters, u.platform
FROM sample s
  JOIN upload u ON s.id = u.sample_id
  JOIN user uu ON uu.id = u.user_id
  JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE s.id = :id LIMIT 1;"
        );
        $p->bindParam(":id", $id, PDO::PARAM_INT);
        $result = false;
        if ($p->execute() && $p->rowCount() === 1) {
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"],
                new User($data["user_id"], $data["user_name"], $data["email"]),
                new CCExtractorVersion($data["ccx_id"], $data["version"], new DateTime($data["released"]),
                    $data["ccx_hash"]
                ),
                $data["platform"], $data["parameters"], $data["notes"]
            );
        }

        return $result;
    }

    /**
     * Gets a sample for the given hash.
     *
     * @param string $hash The sample to get.
     *
     * @return bool|SampleData False on failure, sample data otherwise.
     */
    public function getSampleByHash($hash)
    {
        $p = $this->pdo->prepare("
SELECT s.*, uu.id AS 'user_id', uu.name AS 'user_name', uu.email, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.notes, u.parameters, u.platform
FROM sample s
  JOIN upload u ON s.id = u.sample_id
  JOIN user uu ON uu.id = u.user_id
  JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE s.hash = :hash LIMIT 1;"
        );
        $p->bindParam(":hash", $hash, PDO::PARAM_INT);
        $result = false;
        if ($p->execute() && $p->rowCount() === 1) {
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"],
                new User($data["user_id"], $data["user_name"], $data["email"]),
                new CCExtractorVersion($data["ccx_id"], $data["version"], new DateTime($data["released"]),
                    $data["ccx_hash"]
                ),
                $data["platform"], $data["parameters"], $data["notes"]
            );
        }

        return $result;
    }

    /**
     * Stores a user in the database.
     *
     * @param User $user The user to register/store.
     *
     * @return int The user id assigned, or -1 in case of failure.
     */
    public function registerUser(User $user)
    {
        $name = $user->getName();
        $email = $user->getEmail();
        $hash = $user->getHash();
        $github = $user->isGithub();
        $admin = $user->isAdmin();
        $stmt = $this->pdo->prepare("INSERT INTO user VALUES (NULL,:name,:email,:password,:github,:admin)");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password", $hash, PDO::PARAM_STR);
        $stmt->bindParam(":github", $github, PDO::PARAM_BOOL);
        $stmt->bindParam(":admin", $admin, PDO::PARAM_BOOL);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            return intval($this->pdo->lastInsertId());
        }

        return -1;
    }

    /**
     * Fetches FTP credentials for a given user.
     *
     * @param User $user The user we want to get the FTP credentials off.
     *
     * @return bool|FTPCredentials False on failure, FTPCredentials otherwise.
     */
    public function getFTPCredentialsForUser(User $user)
    {
        $id = $user->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM ftpd WHERE user_id = :uid LIMIT 1;");
        $stmt->bindParam(":uid", $id, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $data = $stmt->fetch();

            return new FTPCredentials($user, $data["user"], $data["status"], $data["password"], $data["dir"],
                $data["ipaccess"], $data["QuotaFiles"]
            );
        }

        return false;
    }

    /**
     * Stores a set of FTP credentials in the database.
     *
     * @param FTPCredentials $newCredentials The FTP credentials to store.
     *
     * @return bool|FTPCredentials False on failure, FTP credentials otherwise.
     */
    public function storeFTPCredentials(FTPCredentials $newCredentials)
    {
        $id = $newCredentials->getUser()->getId();
        $name = $newCredentials->getName();
        $status = $newCredentials->getStatus();
        $password = $newCredentials->getPassword();
        $dir = $newCredentials->getDir();
        $ip_access = $newCredentials->getIpAccess();
        $quota = $newCredentials->getQuotaFiles();
        $stmt = $this->pdo->prepare("INSERT INTO ftpd VALUES (:id,:user,:status,:password,:dir,:ipaccess,:quota);");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":user", $name, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status, PDO::PARAM_STR);
        $stmt->bindParam(":password", $password, PDO::PARAM_STR);
        $stmt->bindParam(":dir", $dir, PDO::PARAM_STR);
        $stmt->bindParam(":ipaccess", $ip_access, PDO::PARAM_STR);
        $stmt->bindParam(":quota", $quota, PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            return $newCredentials;
        }

        return false;
    }

    /**
     * Gets a list of forbidden extensions from the database.
     *
     * @return array A list of forbidden extensions.
     */
    public function getForbiddenExtensions()
    {
        $result = [];
        $stmt = $this->pdo->query("SELECT * FROM blacklist_extension ORDER BY extension ASC;");
        if ($stmt !== false && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            while ($data !== false) {
                $result[] = $data["extension"];
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Adds a given extension to the blacklist.
     *
     * @param string $extension The extension to add to the blacklist.
     *
     * @return bool True on success, false on failure.
     */
    public function addForbiddenExtension($extension)
    {
        $stmt = $this->pdo->prepare("INSERT INTO blacklist_extension VALUES (:extension);");
        $stmt->bindParam(":extension", $extension, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Deletes a given extension from the blacklist.
     *
     * @param string $extension The extension to delete from the blacklist.
     *
     * @return bool True on success, false on failure.
     */
    public function deleteForbiddenExtension($extension)
    {
        $stmt = $this->pdo->prepare("DELETE FROM blacklist_extension WHERE extension = :extension LIMIT 1;");
        $stmt->bindParam(":extension", $extension, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Stores a processing message in the database for given user.
     *
     * @param User $user The user to store the message for.
     * @param string $message The message.
     *
     * @return bool False on failure, true on success.
     */
    public function storeProcessMessage(User $user, $message)
    {
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("INSERT INTO processing_messages VALUES (NULL, :id, :message);");
        $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        $stmt->bindParam(":message", $message, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Stores a given QueuedSample in the database.
     *
     * @param QueuedSample $sample The sample to store.
     *
     * @return bool True on success, false on failure.
     */
    public function storeQueue(QueuedSample $sample)
    {
        $uid = $sample->getUser()->getId();
        $original = $sample->getOriginalName();
        $hash = $sample->getHash();
        $extension = $sample->getExtension();
        $stmt = $this->pdo->prepare("INSERT INTO processing_queued VALUES (NULL, :id, :hash, :extension, :original);");
        $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        $stmt->bindParam(":original", $original, PDO::PARAM_STR);
        $stmt->bindParam(":hash", $hash, PDO::PARAM_STR);
        $stmt->bindParam(":extension", $extension, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Gets all queued samples for given user.
     *
     * @param User $user The user we want the samples for.
     *
     * @return array A list of all queued samples.
     */
    public function getQueuedSamples(User $user = null)
    {
        $setUser = ($user === null);
        if ($setUser) {
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name  FROM processing_queued p JOIN user u ON u.id = p.user_id ORDER BY id ASC;"
            );
        } else {
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :id ORDER BY id ASC");
            $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        }
        $result = [];
        if ($stmt->execute()) {
            $data = $stmt->fetch();
            while ($data !== false) {
                if ($setUser) {
                    $user = new User($data["user_id"], $data["name"], $data["email"]);
                }
                $result[] = new QueuedSample($data["id"], $data["hash"], $data["extension"], $data["original"], $user);
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Gets a queued sample for a given used and id.
     *
     * @param int $id The id of the sample.
     * @param User $user The user that submitted the sample.
     *
     * @return bool|QueuedSample False on failure, the object otherwise.
     */
    public function getQueuedSample($id, User $user = null)
    {
        $setUser = ($user === null);
        if ($setUser) {
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name  FROM processing_queued p JOIN user u ON u.id = p.user_id WHERE p.id = :id LIMIT 1;"
            );
        } else {
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :uid AND id = :id LIMIT 1");
            $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        }
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = false;
        if ($stmt->execute() && $stmt->rowCount() == 1) {
            $data = $stmt->fetch();
            if ($setUser) {
                $user = new User($data["user_id"], $data["name"], $data["email"]);
            }
            $result = new QueuedSample($data["id"], $data["hash"], $data["extension"], $data["original"], $user);
        }

        return $result;
    }

    /**
     * Gets the 10 latest processing messages for a given user.
     *
     * @param User $user The user to get the messages for.
     *
     * @return array The last 10 messages for this user.
     */
    public function getQueuedMessages(User $user = null)
    {
        $setUser = ($user === null);
        if ($setUser) {
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name FROM processing_messages p JOIN user u ON u.id = p.user_id ORDER BY id DESC LIMIT 20;"
            );
        } else {
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT message FROM processing_messages WHERE user_id = :id ORDER BY id DESC LIMIT 10"
            );
            $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        }
        $result = [];
        if ($stmt->execute()) {
            $data = $stmt->fetch();
            while ($data !== false) {
                if ($setUser) {
                    $user = new User($data["user_id"], $data["name"], $data["email"]);
                }
                $result[] = [
                    "message" => $data["message"],
                    "user" => $user
                ];
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Removes a sample from the queue based on given id.
     *
     * @param int $id The id of the queued item to remove.
     *
     * @return bool True on success, false on failure.
     */
    public function removeQueue($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM processing_queued WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Moves a queued item to the sample table, finalizing the entry (apart from additional files).
     *
     * @param User $user The user that submits the sample.
     * @param int $id The id of the queued sample.
     * @param int $ccx_version_id The id of the CCX version used.
     * @param string $platform The platform used.
     * @param string $params The used parameters.
     * @param string $notes Additional remarks or notes.
     *
     * @return bool True on success, false on failure.
     */
    public function moveQueueToSample(User $user, $id, $ccx_version_id, $platform, $params, $notes)
    {
        $queue = $this->getQueuedSample($id, $user);
        if ($queue !== false) {
            $this->pdo->beginTransaction();
            // Insert sample
            $hash = $queue->getHash();
            $extension = $queue->getExtension();
            $original = $queue->getOriginalName();
            $sample = $this->pdo->prepare("INSERT INTO sample VALUES (NULL,:hash,:extension,:original);");
            $sample->bindParam(":hash", $hash, PDO::PARAM_STR);
            $sample->bindParam(":extension", $extension, PDO::PARAM_STR);
            $sample->bindParam(":original", $original, PDO::PARAM_STR);
            if ($sample->execute() && $sample->rowCount() === 1) {
                $sampleId = $this->pdo->lastInsertId();
                $uid = $user->getId();
                // Insert upload
                $upload = $this->pdo->prepare("INSERT INTO upload VALUES (NULL, :uid, :sample, :version, :platform, :params, :notes);"
                );
                $upload->bindParam(":uid", $uid, PDO::PARAM_INT);
                $upload->bindParam(":sample", $sampleId, PDO::PARAM_INT);
                $upload->bindParam(":version", $ccx_version_id, PDO::PARAM_INT);
                $upload->bindParam(":platform", $platform, PDO::PARAM_STR);
                $upload->bindParam(":params", $params, PDO::PARAM_STR);
                $upload->bindParam(":notes", $notes, PDO::PARAM_STR);
                if ($upload->execute() && $upload->rowCount() === 1) {
                    // Remove from queue
                    if ($this->removeQueue($id)) {
                        $this->pdo->commit();

                        return true;
                    }
                }
            }
            $this->pdo->rollBack();
        }

        return false;
    }

    /**
     * Moves a given queued item to the appended sample files.
     *
     * @param AdditionalFile $additional The additional file.
     * @param int $queue_id The id of the queued item.
     *
     * @return null|AdditionalFile Null on failure, the instance with the id added on success.
     */
    public function moveQueueToAppend(AdditionalFile $additional, $queue_id)
    {
        $original = $additional->getOriginalName();
        $extension = $additional->getExtension();
        $sampleId = $additional->getSample()->getId();
        $this->pdo->beginTransaction();
        $upload = $this->pdo->prepare("INSERT INTO additional_file VALUES(NULL,:sample,:original,:extension);");
        $upload->bindParam(":sample", $sampleId, PDO::PARAM_INT);
        $upload->bindParam(":original", $original, PDO::PARAM_STR);
        $upload->bindParam(":extension", $extension, PDO::PARAM_STR);
        if ($upload->execute() && $upload->rowCount() === 1) {
            $id = $this->pdo->lastInsertId();
            // Remove from queue
            if ($this->removeQueue($queue_id)) {
                $this->pdo->commit();
                $additional->setId($id);

                return $additional;
            }
        }
        $this->pdo->rollBack();

        return null;
    }

    /**
     * Gets the additional files for given sample.
     *
     * @param Sample $sample The sample to get the additional files for.
     *
     * @return array A list of additional files for given sample.
     */
    public function getAdditionalFiles(Sample $sample)
    {
        $sampleId = $sample->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM additional_file WHERE sample_id = :id ORDER BY id ASC;");
        $stmt->bindParam(":id", $sampleId, PDO::PARAM_INT);
        $result = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            while ($data !== false) {
                $result[] = new AdditionalFile($data["id"], $sample, $data["original_name"], $data["extension"]);
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    /**
     * Gets the additional file for given sample.
     *
     * @param Sample $sample The sample to get the additional files for.
     * @param int $id The id of the additional file.
     *
     * @return false|AdditionalFile A list of additional files for given sample.
     */
    public function getAdditionalFile(Sample $sample, $id)
    {
        $sampleId = $sample->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM additional_file WHERE sample_id = :sample_id AND id = :id LIMIT 1;");
        $stmt->bindParam(":sample_id", $sampleId, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = false;
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $result = new AdditionalFile($data["id"], $sample, $data["original_name"], $data["extension"]);
        }

        return $result;
    }

    /**
     * Removes given additional file from the database.
     *
     * @param AdditionalFile $additional The additional file to remove
     *
     * @return bool True on success, false on failure.
     */
    public function removeAdditionalFile(AdditionalFile $additional)
    {
        $id = $additional->getId();
        $stmt = $this->pdo->prepare("DELETE FROM additional_file WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Fetches the commit hash for a given CCExtractor version by name.
     *
     * @param string $name The name of the version we want the hash for.
     *
     * @return string An empty string if not found, otherwise the GitHub repository hash for this version.
     */
    public function fetchHashForCCXVersion($name)
    {
        $stmt = $this->pdo->prepare("SELECT hash FROM ccextractor_versions WHERE version = :version LIMIT 1;");
        $stmt->bindParam(":version", $name, PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $data = $stmt->fetch();

            return $data["hash"];
        }

        return "";
    }

    /**
     * Removes a sample with given id from the database.
     *
     * @param int $id The id of the sample to remove.
     *
     * @return bool True on success, false on failure.
     */
    public function removeSample($id)
    {
        if ($this->pdo->beginTransaction()) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            try {
                $stmt = $this->pdo->prepare("DELETE FROM ccextractor_reference WHERE sample_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM regression_tests WHERE sample_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM sample_tests WHERE sample_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM upload WHERE sample_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM sample WHERE id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $this->pdo->commit();

                return true;
            } catch (PDOException $e) {
                $this->pdo->rollBack();
            } finally {
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            }
        }

        return false;
    }

    /**
     * Updates a given sample.
     *
     * @param SampleData $sample The sample to update.
     *
     * @return bool True on success, false on failure.
     */
    public function editSample(SampleData $sample)
    {
        $id = $sample->getId();
        $ccx = $sample->getCCExtractorVersion()->getId();
        $platform = $sample->getPlatform();
        $params = $sample->getParameters();
        $notes = $sample->getNotes();
        // Query
        $stmt = $this->pdo->prepare("UPDATE upload SET ccx_used = :version, platform = :platform, parameters = :params, notes = :notes WHERE sample_id = :id LIMIT 1;"
        );
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":version", $ccx, PDO::PARAM_INT);
        $stmt->bindParam(":platform", $platform, PDO::PARAM_STR);
        $stmt->bindParam(":params", $params, PDO::PARAM_STR);
        $stmt->bindParam(":notes", $notes, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Checks if a given queued sample exists by checking if the hash is in the table.
     *
     * @param string $hash The hash to check for.
     *
     * @return bool True if the hash exists, false otherwise.
     */
    public function queuedSampleExist($hash)
    {
        $stmt = $this->pdo->prepare("SELECT hash FROM processing_queued WHERE hash = :hash LIMIT 1;");
        $stmt->bindParam(":hash", $hash, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
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
    public function store_github_message($id, $message)
    {
        $stmt = $this->pdo->prepare("INSERT INTO github_queue VALUES(NULL,:id,:message);");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":message", $message, PDO::PARAM_STR);
        $stmt->execute();
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
}