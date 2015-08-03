<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use DateTime;
use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\User;
use PDO;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class DatabaseLayer implements ServiceProviderInterface
{
    private $pdo;

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

    public function getLatestCCExtractorVersion(){
        $stmt = $this->pdo->query("SELECT version, released FROM ccextractor_versions ORDER BY ID DESC LIMIT 1;");
        $result = ["version" => "error", "date" => new DateTime()];
        if($stmt !== false){
            $data = $stmt->fetch();
            $result["version"] = $data['version'];
            $result["date"] = new DateTime($data['released']);
        }
        return $result;
    }

    public function getAllCCExtractorVersions(){
        $stmt = $this->pdo->query("SELECT * FROM ccextractor_versions ORDER BY id DESC;");
        $result = [];
        if($stmt !== false && $stmt->rowCount() > 1){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    public function isCCExtractorVersion($id){
        $stmt = $this->pdo->prepare("SELECT id FROM ccextractor_versions WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        return ($stmt->execute() !== false && $stmt->rowCount() == 1);
    }

    public function getAllSamples(){
        $p = $this->pdo->prepare("SELECT * FROM sample ORDER BY extension ASC");
        $result = [];
        if($p->execute()){
            $result = $p->fetchAll();
        }
        return $result;
    }

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

    public function getSamplesForUser(User $user){
        $id = $user->getId();
        $p = $this->pdo->prepare("SELECT s.* FROM sample s JOIN upload u ON s.id = u.sample_id WHERE u.user_id = :id ORDER BY s.id ASC;");
        $p->bindParam(":id",$id,PDO::PARAM_INT);
        $result = [];
        if($p->execute()){
            $result = $p->fetchAll();
        }
        return $result;
    }

    public function getSampleForUser(User $user, $id){
        $uid = $user->getId();
        $p = $this->pdo->prepare("SELECT s.*,u.additional_files FROM sample s JOIN upload u ON s.id = u.sample_id WHERE u.user_id = :uid AND s.id = :id LIMIT 1;");
        $p->bindParam(":uid",$uid,PDO::PARAM_INT);
        $p->bindParam(":id",$id,PDO::PARAM_INT);
        $result = false;
        if($p->execute()){
            $result = $p->fetch();
        }
        return $result;
    }

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
            return $this->pdo->lastInsertId();
        }
        return -1;
    }

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

    public function storeProcessMessage(User $user, $message)
    {
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("INSERT INTO processing_messages VALUES (NULL, :id, :message);");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":message",$message,PDO::PARAM_STR);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function storeQueue(User $user, $original, $hash, $extension)
    {
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("INSERT INTO processing_queued VALUES (NULL, :id, :hash, :extension, :original);");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":original",$original,PDO::PARAM_STR);
        $stmt->bindParam(":hash",$hash,PDO::PARAM_STR);
        $stmt->bindParam(":extension",$extension,PDO::PARAM_STR);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function getQueuedSamples(User $user){
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :id ORDER BY id ASC");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $result = [];
        if($stmt->execute()){
            $result = $stmt->fetchAll();
        }
        return $result;
    }

    public function getQueuedSample(User $user, $id){
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("SELECT * FROM processing_queued WHERE user_id = :uid AND id = :id LIMIT 1");
        $stmt->bindParam(":uid",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        $result = false;
        if($stmt->execute() && $stmt->rowCount() == 1){
            $result = $stmt->fetch();
        }
        return $result;
    }

    public function getQueuedMessages(User $user){
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("SELECT message FROM processing_messages WHERE user_id = :id ORDER BY id DESC LIMIT 10");
        $stmt->bindParam(":id",$uid,PDO::PARAM_INT);
        $result = [];
        if($stmt->execute()){
            $data = $stmt->fetch();
            while($data !== false) {
                $result[] = $data["message"];
                $data = $stmt->fetch();
            }
        }
        return $result;
    }

    public function getQueueFilename(User $user, $id)
    {
        $uid = $user->getId();
        $stmt = $this->pdo->prepare("SELECT hash, extension FROM processing_queued WHERE user_id = :uid AND id = :id LIMIT 1");
        $stmt->bindParam(":uid",$uid,PDO::PARAM_INT);
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        if($stmt->execute() && $stmt->rowCount() === 1){
            $data = $stmt->fetch();
            $name = $data["hash"].".".$data["extension"];
            if($data["extension"] === ""){
                $name = $data["hash"];
            }
            return $name;
        }
        return false;
    }

    public function removeQueue($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM processing_queued WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id",$id,PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function moveQueueToSample(User $user, $id, $ccx_version_id, $platform, $params, $notes)
    {
        $queue = $this->getQueuedSample($user,$id);
        if($queue !== false){
            $this->pdo->beginTransaction();
            // Insert sample
            $sample = $this->pdo->prepare("INSERT INTO sample VALUES (NULL,:hash,:extension,:original);");
            $sample->bindParam(":hash",$queue["hash"],PDO::PARAM_STR);
            $sample->bindParam(":extension",$queue["extension"],PDO::PARAM_STR);
            $sample->bindParam(":original",$queue["original"],PDO::PARAM_STR);
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
}