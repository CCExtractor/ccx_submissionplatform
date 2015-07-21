<?php
/**
 * Created by PhpStorm.
 * User: Willem
 * Date: 21/07/2015
 * Time: 3:47
 */
namespace org\ccextractor\submissionplatform\controllers;

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


    function getPageName()
    {
        return $this->pageName;
    }

    function getPageDescription()
    {
        return $this->pageDescription;
    }
}