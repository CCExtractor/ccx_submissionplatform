<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use Exception;
use org\ccextractor\submissionplatform\objects\User;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class AccountManager implements ServiceProviderInterface
{
    /**
     * @var DatabaseLayer
     */
    private $dba;
    /**
     * @var User
     */
    private $user;

    /**
     * AccountManager constructor.
     */
    public function __construct(DatabaseLayer $dba)
    {
        $this->dba = $dba;
        $this->setup();
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
        $pimple['account'] = $this;
    }

    private function setup(){
        if(!isset($_SESSION)){
            throw new Exception("Session not available!");
        }
        if(!isset($_SESSION["userManager"])){
            // Create default values for the manager
            $this->user = User::getNullUser();
            $this->store();
        } else {
            $this->restore();
        }
    }

    private function restore()
    {
        $d = $_SESSION["userManager"];
        $this->user = new User($d["id"],$d["name"],$d["email"],$d["hash"],$d["github"],$d["admin"]);
    }

    public function store(){
        $_SESSION["userManager"] = [];
        $_SESSION["userManager"]["id"] = $this->user->getId();
        $_SESSION["userManager"]["name"] = $this->user->getName();
        $_SESSION["userManager"]["email"] = $this->user->getEmail();
        $_SESSION["userManager"]["hash"] = $this->user->getHash();
        $_SESSION["userManager"]["github"] = $this->user->isGithub();
        $_SESSION["userManager"]["admin"] = $this->user->isAdmin();
    }

    public function isLoggedIn(){
        return $this->user->getId() > -1;
    }

    public function getUser(){
        return $this->user;
    }

    public function performLogin($email,$password){
        $user = $this->dba->getUserWithEmail($email);
        if($user->getId() > -1){
            // Validate password
            if(password_verify($password,$user->getHash())){
                $this->user = $user;
                $this->store();
                return true;
            }
        }
        return false;
    }

    public function performLogout(){
        $this->user = User::getNullUser();
        $this->store();
    }

    public function findUser($id){
        if($this->user->getId() === $id){
            return $this->user;
        }
        // TODO: finish
        return false;
    }
}