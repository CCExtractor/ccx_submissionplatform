<?php
namespace org\ccextractor\submissionplatform\objects;

/**
 * Class RegressionInputType represent the possible input types for a test.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class RegressionInputType extends BasicEnum
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

    private static $databaseNames = [
        self::FILE => "file",
        self::STDIN => "stdin",
        self::UDP => "udp"
    ];

    /**
     * RegressionInputType constructor.
     */
    public function __construct($enumValue)
    {
        parent::__construct($enumValue, RegressionInputType::__default);
    }


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

    /**
     * @param string $dbFormat
     * @param bool $strict
     *
     * @return RegressionInputType|bool
     */
    public static function fromDatabaseString($dbFormat = "", $strict = false)
    {
        $key = array_search($dbFormat, self::$databaseNames);
        if ($key !== false) {
            return new RegressionInputType($key);
        }

        return $strict ? false : self::getFile();
    }

    /**
     * @return string
     */
    public function toDatabaseString()
    {
        return self::$databaseNames[(int)$this];
    }

    /**
     * @return array
     */
    public static function getAll()
    {
        return [self::getFile(), self::getStdin(), self::getUdp()];
    }

    /**
     * @param $input_type
     *
     * @return bool
     */
    public static function isValid($input_type)
    {
        return array_key_exists($input_type, self::$names);
    }

    /**
     * @return string
     */
    public function toString()
    {
        return self::$names[(int)$this];
    }
}