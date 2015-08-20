<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

/**
 * Class AdditionalFile represents an additional file for a sample.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class AdditionalFile
{
    /**
     * @var int The id of the additional file.
     */
    private $id;
    /**
     * @var Sample The sample this additional file belongs to.
     */
    private $sample;
    /**
     * @var string The original name of this additional file.
     */
    private $original_name;
    /**
     * @var string The extension of this additional file.
     */
    private $extension;

    /**
     * AdditionalFile constructor.
     *
     * @param int $id The id of the additional file.
     * @param Sample $sample The sample this additional file belongs to.
     * @param string $original_name The original name of this additional file.
     * @param string $extension The extension of this additional file.
     */
    public function __construct($id, Sample $sample, $original_name, $extension){
        $this->id = $id;
        $this->sample = $sample;
        $this->original_name = $original_name;
        $this->extension = $extension;
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return Sample
     */
    public function getSample(){
        return $this->sample;
    }

    /**
     * @return string
     */
    public function getOriginalName(){
        return $this->original_name;
    }

    /**
     * @return string
     */
    public function getExtension(){
        return $this->extension;
    }

    /**
     * Gets the filename for this file.
     *
     * @return string The filename
     */
    public function getFileName(){
        return $this->getSample()->getHash()."_".$this->getId().".".$this->getExtension();
    }

    /**
     * @param int $id
     */
    public function setId($id){
        $this->id = $id;
    }

    /**
     * @param Sample $sample
     */
    public function setSample($sample){
        $this->sample = $sample;
    }

    /**
     * @param string $original_name
     */
    public function setOriginalName($original_name){
        $this->original_name = $original_name;
    }

    /**
     * @param string $extension
     */
    public function setExtension($extension){
        $this->extension = $extension;
    }

    public function getShortName($hashLength = 5){
        return substr($this->getSample()->getHash(),0,$hashLength)."_".$this->getId().".".$this->getExtension();
    }
}