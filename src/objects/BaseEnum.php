<?php
namespace org\ccextractor\submissionplatform\objects;

use ReflectionClass;

/**
 * Class BasicEnum represents a base enum for when the SplEnum is unavailable (e.g. PHP7).
 * Adapted class from http://php.net/manual/en/class.splenum.php#117247.
 * 
 * @package org\ccextractor\submissionplatform\objects
 */
abstract class BasicEnum {
    // Cache for lookups
    private static $constCacheArray = NULL;

    // Value for current instance
    /**
     * @var int
     */
    protected $value;

    /**
     * BasicEnum constructor. Can only be called from children's classes.
     *
     * @param int $enumValue The value to init this enum instance with.
     * @param int $default A default, fall-back value to init the instance with if the provided value is not a valid
     * value.
     */
    protected function __construct($enumValue, $default){
        if(!is_int($enumValue)){
            $enumValue = intval($enumValue, 10);
        }
        if(!self::isValidValue($enumValue)){
            // TODO: log illegal value!
            // Return default one
            $enumValue = $default;
        }
        $this->value = $enumValue;
    }

    /**
     * @return array
     */
    public static function getConstants() {
        if (self::$constCacheArray == NULL) {
            self::$constCacheArray = array();
        }
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$constCacheArray)) {
            $reflect = new ReflectionClass($calledClass);
            self::$constCacheArray[$calledClass] = $reflect->getConstants();
        }
        return self::$constCacheArray[$calledClass];
    }

    /**
     * Checks if the given name exists in the list of available enum keys.
     *
     * @param string $key The name to check.
     * @param bool $strict Case strict comparison?
     *
     * @return bool True if the key is found, false otherwise
     */
    public static function isValidName($key, $strict = false) {
        $constants = self::getConstants();

        if ($strict) {
            return array_key_exists($key, $constants);
        }

        $keys = array_map('strtolower', array_keys($constants));
        return in_array(strtolower($key), $keys);
    }

    public static function isValidValue($value) {
        $values = array_values(self::getConstants());
        return in_array($value, $values, $strict = true);
    }

    /**
     * Returns the int value of this enum instance.
     *
     * @return int The int value of this instance.
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getName()
    {
        return array_search($this->value, $this->getConstants());
    }
}