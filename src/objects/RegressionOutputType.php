<?php
namespace org\ccextractor\submissionplatform\objects;

use SplEnum;

class RegressionOutputType extends SplEnum
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

    public static function createFromString($dbFormat="")
    {
        switch($dbFormat){
            case "file":
                return self::getFile();
            case "stdout":
                return self::getStdout();
            case "tcp":
                return self::getTcp();
            case "cea708":
                return self::getCea708();
            case "multiprogram":
                return self::getMultiprogram();
            case "null":
                return self::getNull();
            default:
                return self::getFile();
        }
    }

    public function toString()
    {
        return self::$names[(int)$this];
    }
}