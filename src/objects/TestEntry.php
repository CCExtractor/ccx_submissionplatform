<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;
use DateTime;

/**
 * Class TestEntry holds information about a single progress entry of a test run.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class TestEntry
{
    /**
     * @var string The status of this status entry.
     */
    private $status;
    /**
     * @var string The message of this status entry.
     */
    private $message;
    /**
     * @var DateTime The time of this entry.
     */
    private $timestamp;

    /**
     * TestEntry constructor.
     *
     * @param string $status The status of this status entry.
     * @param string $message The message of this status entry.
     * @param DateTime $timestamp The time of this entry.
     */
    public function __construct($status, $message, DateTime $timestamp){
        $this->status = $status;
        $this->message = $message;
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getStatus(){
        return $this->status;
    }

    /**
     * @return string
     */
    public function getMessage(){
        return $this->message;
    }

    /**
     * @return DateTime
     */
    public function getTimestamp(){
        return $this->timestamp;
    }
}