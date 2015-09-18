<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

/**
 * Class SampleData holds extended data about a sample, like version, platform, notes, ...
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class SampleData extends Sample
{
    /**
     * @var User The user that submitted the sample.
     */
    private $user;
    /**
     * @var CCExtractorVersion The version of CCExtractor that was used.
     */
    private $ccextractor_version;
    /**
     * @var string The used platform.
     */
    private $platform;
    /**
     * @var string The used parameters.
     */
    private $parameters;
    /**
     * @var string Additional notes about the sample.
     */
    private $notes;

    /**
     * SampleData constructor.
     *
     * @param int $id The id of this sample.
     * @param string $hash The hash of the sample.
     * @param string $extension The extension of the sample.
     * @param string $original_name The original name of the sample.
     * @param User $user
     * @param CCExtractorVersion $ccextractor_version
     * @param string $platform
     * @param string $parameters
     * @param string $notes
     */
    public function __construct(
        $id, $hash, $extension, $original_name, User $user, CCExtractorVersion $ccextractor_version,
        $platform = "Windows", $parameters = "", $notes =""
    ){
        parent::__construct($id,$hash,$extension,$original_name);
        $this->user = $user;
        $this->ccextractor_version = $ccextractor_version;
        $this->platform = $platform;
        $this->parameters = $parameters;
        $this->notes = $notes;
    }

    /**
     * @return User
     */
    public function getUser(){
        return $this->user;
    }

    /**
     * @return CCExtractorVersion
     */
    public function getCCExtractorVersion(){
        return $this->ccextractor_version;
    }

    /**
     * @return string
     */
    public function getPlatform(){
        return $this->platform;
    }

    /**
     * @return string
     */
    public function getParameters(){
        return $this->parameters;
    }

    /**
     * @return string
     */
    public function getNotes(){
        return $this->notes;
    }

    /**
     * @param string $notes
     */
    public function setNotes($notes){
        $this->notes = $notes;
    }

    /**
     * @param string $parameters
     */
    public function setParameters($parameters){
        $this->parameters = $parameters;
    }

    /**
     * @param string $platform
     */
    public function setPlatform($platform){
        $this->platform = $platform;
    }

    /**
     * @param CCExtractorVersion $ccextractor_version
     */
    public function setCcextractorVersion($ccextractor_version){
        $this->ccextractor_version = $ccextractor_version;
    }
}