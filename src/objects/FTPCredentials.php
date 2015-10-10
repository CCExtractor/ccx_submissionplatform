<?php
namespace org\ccextractor\submissionplatform\objects;

/**
 * Class FTPCredentials holds an entry from the ftpd table in the database, including password, user, ...
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class FTPCredentials
{
    /**
     * @var User The user linked to these credentials.
     */
    private $user;
    /**
     * @var string The name of the FTP account.
     */
    private $name;
    /**
     * @var int The status of the FTP connection (active/inactive).
     */
    private $status;
    /**
     * @var string The password of the account.
     */
    private $password;
    /**
     * @var string The home directory.
     */
    private $dir;
    /**
     * @var string Limit to certain ip's? * for wildcard.
     */
    private $ip_access;
    /**
     * @var int The maximum number of files in the directory.
     */
    private $QuotaFiles;

    /**
     * FTPCredentials constructor.
     *
     * @param User $user The user linked to these credentials.
     * @param string $name The name of the FTP account.
     * @param int $status The status of the FTP connection (active/inactive).
     * @param string $password The password of the account.
     * @param string $dir The home directory.
     * @param string $ip_access Limit to certain ip's? * for wildcard.
     * @param int $QuotaFiles The maximum number of files in the directory.
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