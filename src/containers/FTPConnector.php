<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\FTPCredentials;
use org\ccextractor\submissionplatform\objects\User;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class FTPConnector implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var DatabaseLayer
     */
    private $dba;

    /**
     * FTPConnector constructor.
     *
     * @param $host
     * @param $port
     */
    public function __construct($host, $port, DatabaseLayer $dba)
    {
        $this->host = $host;
        $this->port = $port;
        $this->dba = $dba;
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
        $pimple["FTPConnector"] = $this;
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