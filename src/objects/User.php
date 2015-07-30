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
        $this->id = intval($id);
        $this->name = $name;
        $this->email = $email;
        $this->hash = $hash;
        $this->github = ($github === "1" || $github === true);
        $this->admin = ($admin === "1" || $admin === true);
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
     *
     * @return int
     */
    public function setId($id)
    {
        $old = $this->id;
        $this->id = $id;
        return $old;
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
     *
     * @return string
     */
    public function setName($name)
    {
        $old = $this->name;
        $this->name = $name;
        return $old;
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
     *
     * @return string
     */
    public function setEmail($email)
    {
        $old = $this->email;
        $this->email = $email;
        return $old;
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
     *
     * @return string
     */
    public function setHash($hash)
    {
        $old = $this->hash;
        $this->hash = $hash;
        return $old;
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
     *
     * @return bool
     */
    public function setGithub($github)
    {
        $old = $this->github;
        $this->github = $github;
        return $old;
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
     *
     * @return bool
     */
    public function setAdmin($admin)
    {
        $old = $this->admin;
        $this->admin = $admin;
        return $old;
    }
}