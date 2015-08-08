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
     * CCExtractorVersion constructor.
     * @param int $id
     * @param string $name
     * @param DateTime $release
     */
    public function __construct($id, $name, DateTime $release){
        $this->id = $id;
        $this->name = $name;
        $this->release = $release;
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

    public static function getNullObject(){
        return new CCExtractorVersion(-1,"",new DateTime());
    }
}