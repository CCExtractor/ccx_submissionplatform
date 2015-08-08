<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

/**
 * Class Sample holds the basic information about a sample.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class Sample
{
    /**
     * @var int The id of the sample.
     */
    protected $id;
    /**
     * @var string The hash of the sample (used as filename).
     */
    protected $hash;
    /**
     * @var string The extension of the sample.
     */
    protected $extension;
    /**
     * @var string The original name of the sample.
     */
    protected $original_name;

    /**
     * Sample constructor.
     *
     * @param int $id The id of this sample.
     * @param string $hash The hash of the sample.
     * @param string $extension The extension of the sample.
     * @param string $original_name The original name of the sample.
     */
    public function __construct($id, $hash, $extension, $original_name = ""){
        $this->id = $id;
        $this->hash = $hash;
        $this->extension = $extension;
        $this->original_name = $original_name;
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
    public function getHash(){
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getExtension(){
        return $this->extension;
    }

    /**
     * @return string
     */
    public function getOriginalName(){
        return $this->original_name;
    }

    /**
     * Creates a full filename based on the name and extension.
     *
     * @param string $name The name of the file.
     * @param string $extension The extension of the file.
     * @return string A concatenated string, based on the given values.
     */
    public static function getFileName($name,$extension){
        return $name.(($extension !== "")?".".$extension:"");
    }

    /**
     * Gets the file name for this sample.
     *
     * @return string The concatenated string of the hash and extension.
     */
    public function getSampleFileName(){
        return self::getFileName($this->getHash(),$this->getExtension());
    }
}