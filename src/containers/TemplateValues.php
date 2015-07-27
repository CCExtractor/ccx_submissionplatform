<?php
/**
 * Created by PhpStorm.
 * User: Willem
 * Date: 27/07/2015
 * Time: 18:28
 */
namespace org\ccextractor\submissionplatform\containers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class TemplateValues implements ServiceProviderInterface
{
    private $values;

    /**
     * TemplateValues constructor.
     */
    public function __construct()
    {
        $this->values = [];
    }

    public function getValues()
    {
        return $this->values;
    }

    public function add($key, $value)
    {
        $this->values[$key] = $value;
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
}