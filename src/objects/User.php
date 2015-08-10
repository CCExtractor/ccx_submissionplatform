<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\objects;

/**
 * Class User represents a user in the system.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class User
{
    /**
     * @var User A null user used in the system.
     */
    private static $nullUser = null;

    /**
     * @var int The id of the user.
     */
    private $id;
    /**
     * @var string The name of the user.
     */
    private $name;
    /**
     * @var string The email address of the user.
     */
    private $email;
    /**
     * @var string The password hash for the user.
     */
    private $hash;
    /**
     * @var bool Is GitHub linked?
     */
    private $github;
    /**
     * @var bool Is the user a admin?
     */
    private $admin;

    /**
     * User constructor.
     *
     * @param int $id The id of the user.
     * @param string $name The name of the user.
     * @param string $email The email address of the user.
     * @param string $hash The password hash for the user.
     * @param bool $github Is GitHub linked?
     * @param bool $admin Is the user a admin?
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

    /**
     * Returns the null user.
     *
     * @return User
     */
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