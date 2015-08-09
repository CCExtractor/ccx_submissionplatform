<?php
/**
 * Created by PhpStorm.
 * User: Willem
 * Date: 21/07/2015
 * Time: 3:47
 */
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\TemplateValues;
use Slim\App;

abstract class BaseController implements IController
{
    public static $BASE_URL;

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

    protected function setDefaultBaseValues(App $app){
        /** @var TemplateValues $tv */
        $tv = $app->templateValues;
        $tv->add("pageName", $this->getPageName());
        $tv->add("pageDescription", $this->getPageDescription());
        $tv->add("isLoggedIn", $app->account->isLoggedIn());
        $tv->add("loggedInUser", $app->account->getUser());
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