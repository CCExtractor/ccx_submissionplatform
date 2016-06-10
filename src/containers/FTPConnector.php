<?php
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\User;

/**
 * Class FTPConnector allows for some actions related to FTP.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class FTPConnector
{
    /**
     * @var string The FTP server host name.
     */
    private $host;
    /**
     * @var int The used FTP port.
     */
    private $port;
    /**
     * @var DatabaseLayer The database connection layer.
     */
    private $dba;

    /**
     * @param string $host The FTP server host name.
     * @param string $port The used FTP port.
     * @param DatabaseLayer $dba The database connection layer.
     */
    public function __construct($host, $port, DatabaseLayer $dba)
    {
        $this->host = $host;
        $this->port = $port;
        $this->dba = $dba;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Fetches the FTP credentials for a given user.
     *
     * @param User $user The user who needs the FTP credentials.
     * @return bool|FTPCredentials False on failure, FTP credentials otherwise.
     */
    public function getFTPCredentialsForUser(User $user){
        // Fetch the username & password for the given user. If non-existing, create them.
        $creds = $this->dba->getFTPCredentialsForUser($user);
        if($creds === false){
            // Create credentials
            $newCredentials = new FTPCredentials($user);
            return $this->dba->storeFTPCredentials($newCredentials);
        }
        return $creds;
    }
}