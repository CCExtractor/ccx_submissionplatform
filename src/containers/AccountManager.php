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
use Slim\Views\Twig;

class AccountManager implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $hmac;
    /**
     * @var DatabaseLayer
     */
    private $dba;
    /**
     * @var EmailLayer
     */
    private $email;
    /**
     * @var User
     */
    private $user;

    /**
     * AccountManager constructor.
     */
    public function __construct(DatabaseLayer $dba, EmailLayer $email, $hmac_key)
    {
        $this->dba = $dba;
        $this->email = $email;
        $this->hmac = $hmac_key;
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
        $user = $this->dba->getUserWithId($id);
        if($user->getId() > -1){
            return $user;
        }
        return false;
    }

    public function sendRecoverEmail(User $user,Twig $twig, $base_url){
        $time = time() + 7200;
        $hmac = $this->getPasswordResetHMAC($user, $time);
        $message = $twig->getEnvironment()->loadTemplate("email/recoveryLink.txt.twig")->render([
            "base_url" => $base_url,
            "time" => $time,
            "hmac" => $hmac,
            "user" => $user
        ]);
        return $this->email->sendEmailToUser($user, "Reset your password", $message);
    }

    /**
     * Generates an HMAC for resetting passwords. Uses the user's ID, password hash, an expiration time and the ip address.
     *
     * @param User $user The user who needs the HMAC
     * @param int $expiration An expiration time in epoch. If left null, current time + 7200 seconds will be taken (2 hours from now).
     *
     * @return string The HMAC that can be used to verify the userId, expires, client IP and old hash.
     */
    public function getPasswordResetHMAC(User $user, $expiration = null)
    {
        if ($expiration === null) {
            $expiration = time() + 7200;
        }
        return hash_hmac(
            "sha256",
            "userId=" . $user->getId() . "&expires=" . $expiration . "&oldHash=" .
            $user->getHash() . "&clientIpAddress=" . $_SERVER['REMOTE_ADDR'],
            $this->hmac);
    }

    public function updatePassword(User $user, $newPassword, Twig $twig){
        // Hash password
        $hash = password_hash($newPassword,PASSWORD_DEFAULT);
        $user->setHash($hash);
        // Store it in the DB
        if($this->dba->updateUser($user)){
            // Send confirmation email
            $message = $twig->getEnvironment()->loadTemplate("email/passwordReset.txt.twig")->render([]);
            return $this->email->sendEmailToUser($user, "Your password has been reset", $message);
        }
        return false;
    }

    public function sendRegistrationEmail($email, Twig $twig, $base_url){
        // Create a 24-hour valid timestamp
        $expiration = time() + 86400;
        // Create HMAC with email & timestamp
        $hmac = $this->getRegistrationEmailHMAC($email,$expiration);
        // Send email
        $message = $twig->getEnvironment()->loadTemplate("email/registration-email.txt.twig")->render([
            "email" => $email,
            "time" => $expiration,
            "hmac" => $hmac,
            "base_url" => $base_url
        ]);
        return $this->email->sendEmail($email, $email, "Email verification for registration request", $message);
    }

    public function getRegistrationEmailHMAC($email, $expiration)
    {
        if ($expiration === null) {
            $expiration = time() + 7200;
        }
        return hash_hmac(
            "sha256",
            "email=" . $email . "&expires=" . $expiration . "&clientIpAddress=" . $_SERVER['REMOTE_ADDR'],
            $this->hmac);
    }

    public function registerUser(User $user, Twig $twig){
        $id = $this->dba->registerUser($user);
        if($id > -1){
            // User stored in DB, send email and log him in
            $user->setId($id);
            $this->user = $user;
            $this->store();
            // Send email
            $message = $twig->getEnvironment()->loadTemplate("email/registration_ok.txt.twig")->render([]);
            return $this->email->sendEmailToUser($user, "Account successfully created", $message);
        }
        return false;
    }
}