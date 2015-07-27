<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use DateTime;
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
        return new User(-1,"","");
    }
}