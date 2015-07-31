<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\objects;

class FTPCredentials
{
    /**
     * @var User
     */
    private $user;
    /**
     * @var string
     */
    private $name;
    /**
     * @var int
     */
    private $status;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $dir;
    /**
     * @var string
     */
    private $ip_access;
    /**
     * @var int
     */
    private $QuotaFiles;

    /**
     * FTPCredentials constructor.
     *
     * @param User $user
     * @param string $name
     * @param int $status
     * @param string $password
     * @param string $dir
     * @param string $ip_access
     * @param int $QuotaFiles
     */
    public function __construct(User $user, $name = "", $status = 1, $password = "", $dir = "", $ip_access = "*", $QuotaFiles = 20)
    {
        $this->user = $user;
        if($name === ""){
            // Generate random username
            $bytes = openssl_random_pseudo_bytes(16);
            $this->name = bin2hex($bytes);
        } else {
            $this->name = $name;
        }
        $this->status = $status;
        if($password === "") {
            // Generate random password
            $bytes = openssl_random_pseudo_bytes(32);
            $this->password = bin2hex($bytes);
        } else {
            $this->password = $password;
        }
        if($dir === ""){
            $this->dir = "/home/".$user->getId();
        } else {
            $this->dir = $dir;
        }
        $this->ip_access = $ip_access;
        $this->QuotaFiles = $QuotaFiles;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @return string
     */
    public function getIpAccess()
    {
        return $this->ip_access;
    }

    /**
     * @return int
     */
    public function getQuotaFiles()
    {
        return $this->QuotaFiles;
    }
}