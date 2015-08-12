<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

use DateTime;

/**
 * Class CCExtractorVersion holds information about a certain CCExtractor release.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class CCExtractorVersion
{
    /**
     * @var int The id of this version.
     */
    private $id;
    /**
     * @var string The name of this version.
     */
    private $name;
    /**
     * @var DateTime The date of the release of this version.
     */
    private $release;
    /**
     * @var string The hash of the GitHub repository of the release.
     */
    private $hash;

    /**
     * CCExtractorVersion constructor.
     *
     * @param int $id The id of this version.
     * @param string $name The name of this version.
     * @param DateTime $release The date of the release of this version.
     * @param string $hash The hash of the GitHub repository of the release.
     */
    public function __construct($id, $name, DateTime $release, $hash){
        $this->id = $id;
        $this->name = $name;
        $this->release = $release;
        $this->hash = $hash;
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(){
        return $this->name;
    }

    /**
     * @return DateTime
     */
    public function getRelease(){
        return $this->release;
    }

    /**
     * @return string
     */
    public function getHash(){
        return $this->hash;
    }

    /**
     * Gets a null object of the CCExtractorVersion object.
     *
     * @return CCExtractorVersion The null object (id: -1)
     */
    public static function getNullObject(){
        return new CCExtractorVersion(-1,"",new DateTime(),"");
    }
}