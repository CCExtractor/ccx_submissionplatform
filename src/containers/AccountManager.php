<?php
namespace org\ccextractor\submissionplatform\containers;

use Exception;
use org\ccextractor\submissionplatform\objects\User;
use org\ccextractor\submissionplatform\objects\UserRole;
use Slim\Views\Twig;

/**
 * Class AccountManager manages user accounts (registration, log in, log out, recovery, ...).
 * @package org\ccextractor\submissionplatform\containers
 */
class AccountManager
{
    /**
     * @var string The HMAC secret.
     */
    private $hmac;
    /**
     * @var DatabaseLayer The object that provides access to the database.
     */
    private $dba;
    /**
     * @var EmailLayer The object that allows emails to be sent.
     */
    private $email;
    /**
     * @var User The current active user.
     */
    private $user;

    /**
     * AccountManager constructor.
     *
     * @param DatabaseLayer $dba The database to connect to
     * @param EmailLayer $email The functionality to send emails
     * @param string $hmac_key The HMAC key that will be used to create the HMAC's.
     * @throws Exception When a session is not available.
     */
    public function __construct(DatabaseLayer $dba, EmailLayer $email, $hmac_key)
    {
        $this->dba = $dba;
        $this->email = $email;
        $this->hmac = $hmac_key;
        $this->setup();
    }

    /**
     * Performs a setup/initialization for the object.
     *
     * @throws Exception When the $_SESSION object is unavailable.
     */
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

    /**
     * Restores a user from the session.
     */
    private function restore()
    {
        $d = $_SESSION["userManager"];
        if(array_key_exists('id', $d) && array_key_exists('name', $d) && array_key_exists('email', $d) &&
            array_key_exists('hash', $d) && array_key_exists('github', $d) && array_key_exists('role', $d)) {
            $this->user = new User($d["id"],$d["name"],$d["email"],$d["hash"],$d["github"],new UserRole($d["role"]));
        } else {
            $this->user = User::getNullUser();
        }
    }

    /**
     * Stores a user in the session variables.
     */
    public function store(){
        $_SESSION["userManager"] = [];
        $_SESSION["userManager"]["id"] = $this->user->getId();
        $_SESSION["userManager"]["name"] = $this->user->getName();
        $_SESSION["userManager"]["email"] = $this->user->getEmail();
        $_SESSION["userManager"]["hash"] = $this->user->getHash();
        $_SESSION["userManager"]["github"] = $this->user->isGithub();
        $_SESSION["userManager"]["role"] = $this->user->getRole()->getValue();
    }

    /**
     * Checks if the current user is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(){
        return $this->user->getId() > -1;
    }

    /**
     * Sets the given user as the current user.
     *
     * @param User $user The new user to set as current user.
     */
    public function setUser(User $user){
        $this->user = $user;
        $this->store();
    }

    /**
     * Gets the current user.
     *
     * @return User The current user.
     */
    public function getUser(){
        return $this->user;
    }

    /**
     * Performs a login attempt for a user with given email and password.
     *
     * @param string $email The email that is used as login name.
     * @param string $password The password for the user linked to the email.
     * @return bool True if the email/password match, false otherwise.
     */
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

    /**
     * Logs out the current user.
     */
    public function performLogout(){
        $this->user = User::getNullUser();
        $this->store();
    }

    /**
     * Searches for a user by the given id.
     *
     * @param int $id The id of the user.
     * @return bool|User The user object if found, false otherwise.
     */
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

    /**
     * Sends a recovery email to a given user.
     *
     * @param User $user The user to send the recovery email to.
     * @param Twig $twig The twig environment to render the email template.
     * @param string $base_url The base url of the application.
     * @return bool True if an email was sent, false otherwise.
     */
    public function sendRecoverEmail(User $user,Twig $twig, $base_url){
        $time = time() + 7200;
        $hmac = $this->getPasswordResetHMAC($user, $time);
        $message = $twig->getEnvironment()->loadTemplate("email/recovery_link.txt.twig")->render([
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

    /**
     * Updates the password for a given user.
     *
     * @param User $user The user that will get the new password.
     * @param string $newPassword The new password for the user.
     * @param Twig $twig The twig environment to render the email template.
     * @return bool True if the password was updated & email sent, false otherwise.
     */
    public function updatePassword(User $user, $newPassword, Twig $twig){
        // Hash password
        $hash = password_hash($newPassword,PASSWORD_DEFAULT);
        $user->setHash($hash);
        // Store it in the DB
        if($this->dba->updateUser($user)){
            // Send confirmation email
            $message = $twig->getEnvironment()->loadTemplate("email/password_reset.txt.twig")->render([]);
            return $this->email->sendEmailToUser($user, "Your password has been reset", $message);
        }
        return false;
    }

    /**
     * Sends a registration email to the given email address, so that the address can be verified.
     *
     * @param string $email The email address that needs to be verified.
     * @param Twig $twig The twig environment to render the email template.
     * @param string $base_url The base url of the application.
     * @return bool True if the email was sent, false otherwise.
     */
    public function sendRegistrationEmail($email, Twig $twig, $base_url){
        // Create a 24-hour valid timestamp
        $expiration = time() + 86400;
        // Create HMAC with email & timestamp
        $hmac = $this->getRegistrationEmailHMAC($email,$expiration);
        // Send email
        $message = $twig->getEnvironment()->loadTemplate("email/registration_email.txt.twig")->render([
            "email" => $email,
            "time" => $expiration,
            "hmac" => $hmac,
            "base_url" => $base_url
        ]);
        return $this->email->sendEmail($email, $email, "Email verification for registration request", $message);
    }

    /**
     * Obtains an HMAC for the registration email.
     *
     * @param string $email The email address that needs to be included in the HMAC.
     * @param int $expiration The expiration time that needs to be included. If left null, time + 86400 seconds will be
     * used (24 hours in the future).
     * @return string The HMAC for the given parameters.
     */
    public function getRegistrationEmailHMAC($email, $expiration = null)
    {
        if ($expiration === null) {
            $expiration = time() + 86400;
        }
        return hash_hmac(
            "sha256",
            "email=" . $email . "&expires=" . $expiration . "&clientIpAddress=" . $_SERVER['REMOTE_ADDR'],
            $this->hmac);
    }

    /**
     * Registers a given user by storing it in the database and sending an email.
     *
     * @param User $user The unregistered user.
     * @param Twig $twig The twig environment to render the email template.
     * @return bool True if the user was registered and an email was sent, false otherwise.
     */
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