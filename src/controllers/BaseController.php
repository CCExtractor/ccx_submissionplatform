<?php
/**
 * Created by PhpStorm.
 * User: Willem
 * Date: 21/07/2015
 * Time: 3:47
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

abstract class BaseController implements IController
{
    protected $pageName;
    protected $pageDescription;

    /**
     * BaseController constructor.
     *
     * @param $name
     */
    protected function __construct($name,$description="")
    {
        $this->pageName = $name;
        $this->pageDescription = $description;
    }

    protected function setDefaultBaseValues(array $base_values, App $app){
        $base_values["pageName"] = $this->getPageName();
        $base_values["pageDescription"] = $this->getPageDescription();

        return $base_values;
    }

    public function getPageName()
    {
        return $this->pageName;
    }

    public function getPageDescription()
    {
        return $this->pageDescription;
    }
}