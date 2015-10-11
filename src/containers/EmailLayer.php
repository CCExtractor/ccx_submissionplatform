<?php
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\User;
use PHPMailer;
use phpmailerException;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class EmailLayer allows to send emails to users.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class EmailLayer implements ServiceProviderInterface
{
    /**
     * @var PHPMailer The PHPMailer that will do the actual email sending.
     */
    private $mail;

    /**
     * EmailLayer constructor.
     *
     * @param string $user
     * @param string $pass
     * @param string $domain
     */
    public function __construct($user, $pass, $domain)
    {
        $this->setUpPHPMailer($user, $pass, $domain);
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
        $pimple["email"] = $this;
    }

    /**
     * Sets up the PHP mailer instance, using the given user, pass and domain.
     *
     * @param string $user The user that will be used to send mail.
     * @param string $pass The password for above user.
     * @param string $domain The domain name.
     * @throws phpmailerException
     */
    private function setUpPHPMailer($user, $pass, $domain)
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        //Enable SMTP debugging
        $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client + server messages
        $mail->Debugoutput = 'html'; //Ask for HTML-friendly debug output
        $mail->Host = 'email-smtp.eu-west-1.amazonaws.com';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls'; // ssl or tls
        $mail->SMTPAuth = true; //Whether to use SMTP authentication
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->setFrom('noreply@'.$domain, $domain);

        $this->mail = $mail;
    }

    /**
     * Sends an email with a given subject and content to a given user, optionally specifying a from user and if the
     * error email (if any) should be send through SES.
     *
     * @param User $user The user to send an email to.
     * @param string $subject The subject of the email.
     * @param string $content The content of the email.
     * @param User $fromUser The user that will be in the Reply-To header (or null for no Reply-To)
     *
     * @return bool Did the email sending succeed?
     */
    public function sendEmailToUser(
        User $user,
        $subject,
        $content,
        User $fromUser = null
    ) {
        if ($fromUser === null) {
            return $this->sendEmail($user->getEmail(), $user->getName(), $subject, $content, null, null);
        } else {
            return  $this->sendEmail($user->getEmail(), $user->getName(), $subject, $content, $fromUser->getEmail(),
                $fromUser->getName());
        }
    }

    /**
     * Sends an email with a given subject and content to a given person, optionally specifying a from user and if the
     * error email (if any) should be send through SES.
     *
     * @param string $to The email address of the user to send an email to.
     * @param string $toName The name of the user to send an email to.
     * @param string $subject The subject of the email.
     * @param string $content The content of the email.
     * @param string $from The email address of the user that will be in the Reply-To header.
     * @param string $fromName The name of the user that will be in the Reply-To header.
     *
     * @return bool Did the email sending succeed?
     */
    public function sendEmail(
        $to,
        $toName,
        $subject,
        $content,
        $from = null,
        $fromName = null
    ) {
        $mail = $this->mail;
        if ($from != null && $fromName != null) {
            $mail->addReplyTo($from, $fromName);
        }
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(false);
        $mail->Body = $content;
        $sent = $mail->send();

        return $sent;
    }

    /**
     * Sends an email to multiple users.
     *
     * @param array $users The users that will receive the email.
     * @param string $subject The subject of the email.
     * @param string $content The content of the email.
     * @param User $fromUser The user that will be in the Reply-To header (or null for no Reply-To)
     *
     * @return bool Did the email sending succeed?
     */
    public function sendEmails(array $users, $subject, $content, User $fromUser = null)
    {
        if ($fromUser == null) {
            return self::sendEmailToUsers($users, $subject, $content);
        } else {
            return self::sendEmail($users, $subject, $content, $fromUser->getEmail(), $fromUser->getName());
        }
    }

    /**
     * Sends an email to multiple users.
     *
     * @param array $users The users that will receive the email.
     * @param string $subject The subject of the email.
     * @param string $content The content of the email.
     * @param string $from The email address of the user that will be in the Reply-To header.
     * @param string $fromName The name of the user that will be in the Reply-To header.
     *
     * @return bool Did the email sending succeed?
     */
    public function sendEmailToUsers(
        array $users,
        $subject,
        $content,
        $from = null,
        $fromName = null
    ) {
        $mail = $this->mail;
        if ($from != null && $fromName != null) {
            $mail->addReplyTo($from, $fromName);
        }
        /** @var User $user */
        foreach ($users as $user) {
            $mail->addAddress($user->getEmail(), $user->getName());
        }
        $mail->Subject = $subject;
        $mail->isHTML(false);
        $mail->Body = $content;
        $sent = $mail->send();

        return $sent;
    }
}