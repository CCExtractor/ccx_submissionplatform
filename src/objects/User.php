<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\objects;

class User
{
    /**
     * @var User
     */
    private static $nullUser = null;

    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $email;
    /**
     * @var string
     */
    private $hash;
    /**
     * @var bool
     */
    private $github;
    /**
     * @var bool
     */
    private $admin;

    /**
     * User constructor.
     *
     * @param int $id
     * @param string $name
     * @param string $email
     * @param string $hash
     * @param bool $github
     * @param bool $admin
     */
    public function __construct($id, $name, $email, $hash="", $github=false, $admin=false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->hash = $hash;
        $this->github = $github;
        $this->admin = $admin;
    }

    public static function getNullUser()
    {
        if(self::$nullUser === null){
            self::$nullUser = new User(-1,"","");
        }
        return self::$nullUser;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return boolean
     */
    public function isGithub()
    {
        return $this->github;
    }

    /**
     * @param boolean $github
     */
    public function setGithub($github)
    {
        $this->github = $github;
    }

    /**
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->admin;
    }

    /**
     * @param boolean $admin
     */
    public function setAdmin($admin)
    {
        $this->admin = $admin;
    }
}