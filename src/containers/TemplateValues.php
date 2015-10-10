<?php
namespace org\ccextractor\submissionplatform\containers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class TemplateValues keeps track of the values that are used for template rendering.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class TemplateValues implements ServiceProviderInterface
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
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple An Container instance
     */
    public function register(Container $pimple)
    {
        $pimple["templateValues"] = $this;
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