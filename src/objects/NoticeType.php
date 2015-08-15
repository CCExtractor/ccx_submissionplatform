<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

use SplEnum;
use SplType;

/**
 * Class NoticeType represents the possible types of an notice
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class NoticeType extends SplEnum
{
    // Default is error.
    const __default = 1;
    // Error notice
    const ERROR = 1;
    // Warning notice
    const WARNING = 2;
    // Pass/ok notice
    const SUCCESS = 3;

    // Static fields for shortcut usage instead of constructor.
    private static $ok = null;
    private static $warning = null;
    private static $error = null;

    /**
     * Gets the Success instance of this Enum.
     *
     * @return NoticeType
     */
    public static function getSuccess(){
        if(self::$ok === null){
            self::$ok = new NoticeType(NoticeType::SUCCESS);
        }
        return self::$ok;
    }

    /**
     * Gets the Warning instance of this Enum.
     *
     * @return NoticeType
     */
    public static function getWarning(){
        if(self::$warning === null){
            self::$warning = new NoticeType(NoticeType::WARNING);
        }
        return self::$warning;
    }

    /**
     * Gets the Error instance of this Enum.
     *
     * @return NoticeType
     */
    public static function getError(){
        if(self::$error === null){
            self::$error = new NoticeType(NoticeType::ERROR);
        }
        return self::$error;
    }
}