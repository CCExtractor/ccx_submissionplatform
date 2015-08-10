<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\TemplateValues;
use Slim\App;

/**
 * Class BaseController provides some base methods that can be used by all Controllers that inherit from this class.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
abstract class BaseController implements IController
{
    /**
     * @var string The base url (e.g. http://domain.tld) for the application.
     */
    public static $BASE_URL;
    /**
     * @var string The page name of this controller.
     */
    protected $pageName;
    /**
     * @var string The page description of this controller.
     */
    protected $pageDescription;

    /**
     * The BaseController parent constructor. Should by called by classes that inherit from this class.
     *
     * @param string $name The name of this page/controller.
     * @param string $description The page description of this page/controller.
     */
    protected function __construct($name,$description="")
    {
        $this->pageName = $name;
        $this->pageDescription = $description;
    }

    /**
     * Sets a set of default values for the template renderer that are required in every template.
     *
     * @param App $app The instance of the Slim framework app.
     */
    protected function setDefaultBaseValues(App $app){
        /** @var TemplateValues $tv */
        $tv = $app->templateValues;
        $tv->add("pageName", $this->getPageName());
        $tv->add("pageDescription", $this->getPageDescription());
        $tv->add("isLoggedIn", $app->account->isLoggedIn());
        $tv->add("loggedInUser", $app->account->getUser());
    }

    /**
     * Returns the name of the controller/page.
     *
     * @return string The name of the page
     */
    public function getPageName()
    {
        return $this->pageName;
    }
    /**
     * Gets the page description.
     *
     * @return string The description of the page.
     */
    public function getPageDescription()
    {
        return $this->pageDescription;
    }
}