<?php
namespace org\ccextractor\submissionplatform\objects;

use SplEnum;

class RegressionOutputType extends BasicEnum
{
    // Default is file.
    const __default = 1;
    // Output saved in file
    const FILE = 1;
    // Output to stdout
    const STDOUT = 2;
    // Output passed to TCP
    const TCP = 3;
    // No output
    const NULL = 4;
    // Output comes from multiprogram
    const MULTIPROGRAM = 5;
    // Output comes from CEA708.
    const CEA708 = 6;

    // Static fields for shortcut usage instead of constructor.
    private static $file = null;
    private static $stdout = null;
    private static $tcp = null;
    private static $null = null;
    private static $multiprogram = null;
    private static $cea708 = null;

    private static $names = [
        self::FILE => "file",
        self::STDOUT => "stdout",
        self::TCP => "tcp",
        self::NULL => "null",
        self::MULTIPROGRAM => "multi-program",
        self::CEA708 => "CEA-708"
    ];

    private static $databaseNames = [
        self::FILE => "file",
        self::STDOUT => "stdout",
        self::TCP => "tcp",
        self::NULL => "null",
        self::MULTIPROGRAM => "multiprogram",
        self::CEA708 => "cea708"
    ];

    /**
     * RegressionOutputType constructor.
     */
    public function __construct($enumValue)
    {
        parent::__construct($enumValue, RegressionOutputType::__default);
    }


    /**
     * Gets the File instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getFile()
    {
        if (self::$file === null) {
            self::$file = new RegressionOutputType(RegressionOutputType::FILE);
        }

        return self::$file;
    }

    /**
     * Gets the Stdout instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getStdout()
    {
        if (self::$stdout === null) {
            self::$stdout = new RegressionOutputType(RegressionOutputType::STDOUT);
        }

        return self::$stdout;
    }

    /**
     * Gets the TCP instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getTcp()
    {
        if (self::$tcp === null) {
            self::$tcp = new RegressionOutputType(RegressionOutputType::TCP);
        }

        return self::$tcp;
    }

    /**
     * Gets the Null instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getNull()
    {
        if (self::$null === null) {
            self::$null = new RegressionOutputType(RegressionOutputType::NULL);
        }

        return self::$null;
    }

    /**
     * Gets the Multiprogram instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getMultiprogram()
    {
        if (self::$multiprogram === null) {
            self::$multiprogram = new RegressionOutputType(RegressionOutputType::MULTIPROGRAM);
        }

        return self::$multiprogram;
    }

    /**
     * Gets the CEA708 instance of this type.
     *
     * @return RegressionOutputType
     */
    public static function getCea708()
    {
        if (self::$cea708 === null) {
            self::$cea708 = new RegressionOutputType(RegressionOutputType::CEA708);
        }

        return self::$cea708;
    }

    /**
     * @param string $dbFormat
     * @param bool $strict
     *
     * @return bool|RegressionOutputType
     */
    public static function fromDatabaseString($dbFormat = "", $strict = false)
    {
        $key = array_search($dbFormat, self::$databaseNames);
        if ($key !== false) {
            return new RegressionOutputType($key);
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
        return [
            self::getFile(),
            self::getStdout(),
            self::getTcp(),
            self::getCea708(),
            self::getMultiprogram(),
            self::getNull()
        ];
    }

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