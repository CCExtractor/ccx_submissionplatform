<?php
namespace org\ccextractor\submissionplatform\objects;

use SplEnum;

/**
 * Class RegressionInputType represent the possible input types for a test.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class RegressionInputType extends SplEnum
{
    // Default is file.
    const __default = 1;
    // Regular file, passed as command line
    const FILE = 1;
    // File, fed through stdin
    const STDIN = 2;
    // File, fed through UDP
    const UDP = 3;

    // Static fields for shortcut usage instead of constructor.
    private static $file = null;
    private static $stdin = null;
    private static $udp = null;

    private static $names = [
        self::FILE => "file",
        self::STDIN => "stdin",
        self::UDP => "udp"
    ];

    /**
     * Gets the File instance of this type.
     *
     * @return RegressionInputType
     */
    public static function getFile()
    {
        if (self::$file === null) {
            self::$file = new RegressionInputType(RegressionInputType::FILE);
        }

        return self::$file;
    }

    /**
     * Gets the Stdin instance of this type.
     *
     * @return RegressionInputType
     */
    public static function getStdin()
    {
        if (self::$stdin === null) {
            self::$stdin = new RegressionInputType(RegressionInputType::STDIN);
        }

        return self::$stdin;
    }

    /**
     * Gets the UDP instance of this type.
     *
     * @return RegressionInputType
     */
    public static function getUdp()
    {
        if (self::$udp === null) {
            self::$udp = new RegressionInputType(RegressionInputType::UDP);
        }

        return self::$udp;
    }

    public static function createFromString($dbFormat="")
    {
        switch($dbFormat){
            case "file":
                return self::getFile();
            case "stdin":
                return self::getStdin();
            case "udp":
                return self::getUdp();
            default:
                return self::getFile();
        }
    }

    public function toString()
    {
        return self::$names[(int)$this];
    }
}