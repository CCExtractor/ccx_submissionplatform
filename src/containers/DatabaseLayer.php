<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use DateTime;
use org\ccextractor\submissionplatform\objects\CCExtractorVersion;
use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\QueuedSample;
use org\ccextractor\submissionplatform\objects\Sample;
use org\ccextractor\submissionplatform\objects\SampleData;
use org\ccextractor\submissionplatform\objects\User;
use PDO;
use PDOException;
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
    public function getLatestCCExtractorVersion(){
        $stmt = $this->pdo->query("SELECT * FROM ccextractor_versions ORDER BY ID DESC LIMIT 1;");
        if($stmt !== false){
            $data = $stmt->fetch();
            $result = new CCExtractorVersion($data["id"],$data["version"],new DateTime($data["released"]),$data["hash"]);
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
    public function getAllCCExtractorVersions(){
        $stmt = $this->pdo->query("SELECT * FROM ccextractor_versions ORDER BY id DESC;");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 1){
            $data = $stmt->fetch();
            while($data !== false){
                $result[] = new CCExtractorVersion($data["id"],$data["version"],new DateTime($data["released"]),$data["hash"]);
                $data = $stmt->fetch();
            }
        }
        return $result;
    }

    /**
     * Checks if a CCExtractor version exists with given id.
     *
     * @param int $id The id of the CCExtractor version to check.
     * @return bool True if there exists a version with given id.
     */
    public function isCCExtractorVersion($id){
        $stmt = $this->pdo->prepare("SELECT id FROM ccextractor_versions WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        return ($stmt->execute() !== false && $stmt->rowCount() == 1);
    }

    /**
     * Gets all samples, sorted by extension.
     *
     * @return array The samples.
     */
    public function getAllSamples(){
        $p = $this->pdo->prepare("SELECT * FROM sample ORDER BY extension ASC");
        $result = [];
        if($p->execute()){
            $data = $p->fetch();
            while($data !== false){
                $result[] = new Sample($data["id"],$data["hash"],$data["extension"],$data["original_name"]);
                $data = $p->fetch();
            }
        }
        return $result;
    }

    /**
     * Gets a user that has the given email address.
     *
     * @param string $email The email address we want a user for.
     * @return User A user object if found, or the null user.
     */
    public function getUserWithEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE email = :email LIMIT 1");
        $stmt->bindParam(":email",$email,PDO::PARAM_STR);
        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            return new User($data["id"],$data["name"],$data["email"],$data["password"],$data["github_linked"],$data["admin"]);
        }
        return User::getNullUser();
    }

    /**
     * Returns a user with a given id, or the null user if not found.
     *
     * @param int $id The id of the user.
     * @return User The user object.
     */
    public function getUserWithId($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            return new User($data["id"],$data["name"],$data["email"],$data["password"],$data["github_linked"],$data["admin"]);
        }
        return User::getNullUser();
    }

    /**
     * Gets a list of all registered users.
     *
     * @return array A list of all registered users.
     */
    public function listUsers(){
        $stmt = $this->pdo->query("SELECT * FROM user ORDER BY id ASC");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 0){
            $data = $stmt->fetch();
            while($data !== false){
                $result[] = new User($data["id"],$data["name"],$data["email"],$data["password"],$data["github_linked"],$data["admin"]);
                $data = $stmt->fetch();
            }
        }
        return $result;
    }

    /**
     * Stores a modified user object in the database.
     *
     * @param User $user The user object that's modified compared to the database entry.
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
        $stmt = $this->pdo->prepare("UPDATE user SET name = :name, email = :email, password = :password, github_linked = :github, admin = :admin WHERE id = :id");
        $stmt->bindParam(":name",$name,PDO::PARAM_STR);
        $stmt->bindParam(":email",$email,PDO::PARAM_STR);
        $stmt->bindParam(":password",$hash,PDO::PARAM_STR);
        $stmt->bindParam(":github",$github,PDO::PARAM_BOOL);
        $stmt->bindParam(":admin",$admin,PDO::PARAM_BOOL);
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);

        if($stmt->execute()){
            return $stmt->rowCount() === 1;
        }
        return false;
    }

    /**
     * Gets all samples for a given user.
     *
     * @param User $user The user we want to see the samples of.
     * @return array A list of the submitted samples.
     */
    public function getSamplesForUser(User $user){
        $id = $user->getId();
        $p = $this->pdo->prepare("SELECT s.* FROM sample s JOIN upload u ON s.id = u.sample_id WHERE u.user_id = :id ORDER BY s.id ASC;");
        $p->bindParam(":id",$id,PDO::PARAM_INT);
        $result = [];
        if($p->execute()){
            $data = $p->fetch();
            while($data !== false){
                $result[] = new Sample($data["id"],$data["hash"],$data["extension"],$data["original_name"]);
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
     * @return bool|SampleData False on failure or the SampleData on success.
     */
    public function getSampleForUser(User $user, $id){
        $uid = $user->getId();
        $p = $this->pdo->prepare("
SELECT s.*, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.additional_files, u.notes, u.parameters, u.platform
FROM sample s
	JOIN upload u ON s.id = u.sample_id
	JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE u.user_id = :uid AND s.id = :id LIMIT 1;");
        $p->bindParam(":uid",$uid,PDO::PARAM_INT);
        $p->bindParam(":id",$id,PDO::PARAM_INT);
        $result = false;
        if($p->execute()){
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"], $user,
                new CCExtractorVersion($data["ccx_id"],$data["version"],new DateTime($data["released"]),$data["ccx_hash"]),
                $data["platform"], $data["parameters"], $data["notes"], $data["additional_files"]
            );
        }
        return $result;
    }

    /**
     * Gets a sample for the given id.
     *
     * @param int $id The sample to get.
     * @return bool|SampleData False on failure, sample data otherwise.
     */
    public function getSampleById($id){
        $p = $this->pdo->prepare("
SELECT s.*, uu.id AS 'user_id', uu.name AS 'user_name', uu.email, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.additional_files, u.notes, u.parameters, u.platform
FROM sample s
  JOIN upload u ON s.id = u.sample_id
  JOIN user uu ON uu.id = u.user_id
  JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE s.id = :id LIMIT 1;");
        $p->bindParam(":id",$id,PDO::PARAM_INT);
        $result = false;
        if($p->execute()){
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"],
                new User($data["user_id"],$data["user_name"],$data["email"]),
                new CCExtractorVersion($data["ccx_id"],$data["version"],new DateTime($data["released"]),$data["ccx_hash"]),
                $data["platform"], $data["parameters"], $data["notes"], $data["additional_files"]
            );
        }
        return $result;
    }

    /**
     * Gets a sample for the given hash.
     *
     * @param string $hash The sample to get.
     * @return bool|SampleData False on failure, sample data otherwise.
     */
    public function getSampleByHash($hash){
        $p = $this->pdo->prepare("
SELECT s.*, uu.id AS 'user_id', uu.name AS 'user_name', uu.email, c.id AS 'ccx_id', c.released, c.version, c.hash AS 'ccx_hash', u.additional_files, u.notes, u.parameters, u.platform
FROM sample s
  JOIN upload u ON s.id = u.sample_id
  JOIN user uu ON uu.id = u.user_id
  JOIN ccextractor_versions c ON c.id = u.ccx_used
WHERE s.hash = :hash LIMIT 1;");
        $p->bindParam(":hash",$hash,PDO::PARAM_INT);
        $result = false;
        if($p->execute()){
            $data = $p->fetch();
            $result = new SampleData(
                $data["id"], $data["hash"], $data["extension"], $data["original_name"],
                new User($data["user_id"],$data["user_name"],$data["email"]),
                new CCExtractorVersion($data["ccx_id"],$data["version"],new DateTime($data["released"]),$data["ccx_hash"]),
                $data["platform"], $data["parameters"], $data["notes"], $data["additional_files"]
            );
        }
        return $result;
    }

    /**
     * Stores a user in the database.
     *
     * @param User $user The user to register/store.
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
        $stmt->bindParam(":name",$name,PDO::PARAM_STR);
        $stmt->bindParam(":email",$email,PDO::PARAM_STR);
        $stmt->bindParam(":password",$hash,PDO::PARAM_STR);
        $stmt->bindParam(":github",$github,PDO::PARAM_BOOL);
        $stmt->bindParam(":admin",$admin,PDO::PARAM_BOOL);
        if($stmt->execute() && $stmt->rowCount() === 1){
            return intval($this->pdo->lastInsertId());
        }
        return -1;
    }

    /**
     * Fetches FTP credentials for a given user.
     *
     * @param User $user The user we want to get the FTP credentials off.
     * @return bool|FTPCredentials False on failure, FTPCredentials otherwise.
     */
    public function getFTPCredentialsForUser(User $user){
        $id = $user->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM ftpd WHERE user_id = :uid LIMIT 1;");
        $stmt->bindParam(":uid",$id,PDO::PARAM_INT);
        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            return new FTPCredentials($user,$data["user"],$data["status"],$data["password"],$data["dir"],$data["ipaccess"],$data["QuotaFiles"]);
        }
        return false;
    }

    /**
     * Stores a set of FTP credentials in the database.
     *
     * @param FTPCredentials $newCredentials The FTP credentials to store.
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
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        $stmt->bindParam(":user",$name,PDO::PARAM_STR);
        $stmt->bindParam(":status",$status,PDO::PARAM_STR);
        $stmt->bindParam(":password",$password,PDO::PARAM_STR);
        $stmt->bindParam(":dir",$dir,PDO::PARAM_STR);
        $stmt->bindParam(":ipaccess",$ip_access,PDO::PARAM_STR);
        $stmt->bindParam(":quota",$quota,PDO::PARAM_INT);
        if($stmt->execute() && $stmt->rowCount() === 1){
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
        if($stmt !== false && $stmt->rowCount() > 0){
            $data = $stmt->fetch();
            while($data !== false){
                $result[] = $data["extension"];
                $data = $stmt->fetch();
            }
        }
        return $result;
    }

    /**
     * Stores a processing message in the database for given user.
     *
     * @param User $user The user to store the message for.
     * @param string $message The message.
     * @return bool False on failure, true on success.
     */
    public function storeProcessMessage(User $user, $message)
    {
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("INSERT INTO processing_messages VALUES (NULL, :id, :message);");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":message",$message,PDO::PARAM_STR);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Stores a given QueuedSample in the database.
     *
     * @param QueuedSample $sample The sample to store.
     * @return bool True on success, false on failure.
     */
    public function storeQueue(QueuedSample $sample)
    {
        $uid = $sample->getUser()->getId();
        $original = $sample->getOriginalName();
        $hash = $sample->getHash();
        $extension = $sample->getExtension();
        $stmt = $this->pdo->prepare("INSERT INTO processing_queued VALUES (NULL, :id, :hash, :extension, :original);");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":original",$original,PDO::PARAM_STR);
        $stmt->bindParam(":hash",$hash,PDO::PARAM_STR);
        $stmt->bindParam(":extension",$extension,PDO::PARAM_STR);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * Gets all queued samples for given user.
     *
     * @param User $user The user we want the samples for.
     * @return array A list of all queued samples.
     */
    public function getQueuedSamples(User $user = null){
        $setUser = ($user === null);
        if($setUser){
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name  FROM processing_queued p JOIN user u ON u.id = p.user_id ORDER BY id ASC;");
        } else{
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :id ORDER BY id ASC");
            $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        }
        $result = [];
        if($stmt->execute()){
            $data = $stmt->fetch();
            while($data !== false){
                if($setUser){
                    $user = new User($data["user_id"],$data["name"],$data["email"]);
                }
                $result[] = new QueuedSample($data["id"],$data["hash"],$data["extension"],$data["original"],$user);
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
     * @return bool|QueuedSample False on failure, the object otherwise.
     */
    public function getQueuedSample($id, User $user = null){
        $setUser = ($user === null);
        if($setUser){
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name  FROM processing_queued p JOIN user u ON u.id = p.user_id WHERE p.id = :id LIMIT 1;");
        } else {
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :uid AND id = :id LIMIT 1");
            $stmt->bindParam(":uid", $uid, PDO::PARAM_INT);
        }
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        $result = false;
        if($stmt->execute() && $stmt->rowCount() == 1){
            $data = $stmt->fetch();
            if($setUser){
                $user = new User($data["user_id"],$data["name"],$data["email"]);
            }
            $result = new QueuedSample($data["id"],$data["hash"],$data["extension"],$data["original"],$user);
        }
        return $result;
    }

    /**
     * Gets the 10 latest processing messages for a given user.
     *
     * @param User $user The user to get the messages for.
     * @return array The last 10 messages for this user.
     */
    public function getQueuedMessages(User $user = null){
        $setUser = ($user === null);
        if($setUser){
            $stmt = $this->pdo->prepare("SELECT p.*,u.email,u.name FROM processing_messages p JOIN user u ON u.id = p.user_id ORDER BY id DESC LIMIT 20;");
        } else{
            $uid = $user->getId();
            $stmt = $this->pdo->prepare("SELECT message FROM processing_messages WHERE user_id = :id ORDER BY id DESC LIMIT 10");
            $stmt->bindParam(":id", $uid, PDO::PARAM_INT);
        }
        $result = [];
        if($stmt->execute()){
            $data = $stmt->fetch();
            while($data !== false) {
                if($setUser){
                    $user = new User($data["user_id"],$data["name"],$data["email"]);
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
     * @return bool True on success, false on failure.
     */
    public function removeQueue($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM processing_queued WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
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
     * @return bool True on success, false on failure.
     */
    public function moveQueueToSample(User $user, $id, $ccx_version_id, $platform, $params, $notes)
    {
        $queue = $this->getQueuedSample($id, $user);
        if($queue !== false){
            $this->pdo->beginTransaction();
            // Insert sample
            $hash = $queue->getHash();
            $extension = $queue->getExtension();
            $original = $queue->getOriginalName();
            $sample = $this->pdo->prepare("INSERT INTO sample VALUES (NULL,:hash,:extension,:original);");
            $sample->bindParam(":hash",$hash,PDO::PARAM_STR);
            $sample->bindParam(":extension",$extension,PDO::PARAM_STR);
            $sample->bindParam(":original",$original,PDO::PARAM_STR);
            if($sample->execute() && $sample->rowCount() === 1){
                $sampleId = $this->pdo->lastInsertId();
                $uid = $user->getId();
                // Insert upload
                $upload = $this->pdo->prepare("INSERT INTO upload VALUES (NULL, :uid, :sample, :version, :platform, :params, :notes, 0);");
                $upload->bindParam(":uid",$uid,PDO::PARAM_INT);
                $upload->bindParam(":sample",$sampleId,PDO::PARAM_INT);
                $upload->bindParam(":version",$ccx_version_id,PDO::PARAM_INT);
                $upload->bindParam(":platform",$platform,PDO::PARAM_STR);
                $upload->bindParam(":params",$params,PDO::PARAM_STR);
                $upload->bindParam(":notes",$notes,PDO::PARAM_STR);
                if($upload->execute() && $upload->rowCount() === 1){
                    // Remove from queue
                    if($this->removeQueue($id)){
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
     * @param int $queue_id The id of the queued item.
     * @param int $sample_id The id of the sample where the queued item will be added to.
     * @return bool
     */
    public function moveQueueToAppend($queue_id, $sample_id)
    {
        $this->pdo->beginTransaction();
        $upload = $this->pdo->prepare("UPDATE upload SET additional_files = additional_files + 1 WHERE sample_id = :id");
        $upload->bindParam(":id",$sample_id,PDO::PARAM_INT);
        if($upload->execute() && $upload->rowCount() === 1){
            // Remove from queue
            if($this->removeQueue($queue_id)){
                $this->pdo->commit();
                return true;
            }
        }
        $this->pdo->rollBack();
        return false;
    }

    /**
     * Fetches the commit hash for a given CCExtractor version by name.
     *
     * @param string $name The name of the version we want the hash for.
     * @return string An empty string if not found, otherwise the GitHub repository hash for this version.
     */
    public function fetchHashForCCXVersion($name){
        $stmt = $this->pdo->prepare("SELECT hash FROM ccextractor_versions WHERE version = :version LIMIT 1;");
        $stmt->bindParam(":version",$name,PDO::PARAM_STR);

        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            return $data["hash"];
        }
        return "";
    }

    /**
     * Removes a sample with given id from the database.
     *
     * @param int $id The id of the sample to remove.
     * @return bool True on success, false on failure.
     */
    public function removeSample($id){
        if($this->pdo->beginTransaction()){
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            try{
                $stmt = $this->pdo->prepare("DELETE FROM ccextractor_reference WHERE sample_id = :id;");
                $stmt->bindParam(":id",$id,PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM regression_tests WHERE sample_id = :id;");
                $stmt->bindParam(":id",$id,PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM sample_tests WHERE sample_id = :id;");
                $stmt->bindParam(":id",$id,PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM upload WHERE sample_id = :id;");
                $stmt->bindParam(":id",$id,PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM sample WHERE id = :id;");
                $stmt->bindParam(":id",$id,PDO::PARAM_INT);
                $stmt->execute();
                $this->pdo->commit();
                return true;
            } catch(PDOException $e){
                $this->pdo->rollBack();
            } finally {
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT);
            }
        }
        return false;
    }

    /**
     * Updates a given sample.
     *
     * @param SampleData $sample The sample to update.
     * @return bool True on success, false on failure.
     */
    public function editSample(SampleData $sample){
        $id = $sample->getId();
        $ccx = $sample->getCCExtractorVersion()->getId();
        $platform = $sample->getPlatform();
        $params = $sample->getParameters();
        $notes = $sample->getNotes();
        // Query
        $stmt = $this->pdo->prepare("UPDATE upload SET ccx_used = :version, platform = :platform, parameters = :params, notes = :notes WHERE sample_id = :id LIMIT 1;");
        $stmt->bindParam(":id",$id);
        $stmt->bindParam(":version",$ccx);
        $stmt->bindParam(":platform",$platform);
        $stmt->bindParam(":params",$params);
        $stmt->bindParam(":notes",$notes);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }
}