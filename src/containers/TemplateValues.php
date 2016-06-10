<?php
namespace org\ccextractor\submissionplatform\containers;

/**
 * Class TemplateValues keeps track of the values that are used for template rendering.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class TemplateValues
{
    /**
     * @var array The values that will be used for template rendering
     */
    private $values;

    /**
     * TemplateValues constructor.
     */
    public function __construct()
    {
        $this->values = [];
    }

    /**
     * @return array The currently registered values.
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Adds a key-value pair to the array of values.
     *
     * @param string $key The key.
     * @param object|string $value The value.
     */
    public function add($key, $value)
    {
        $this->values[$key] = $value;
    }
}