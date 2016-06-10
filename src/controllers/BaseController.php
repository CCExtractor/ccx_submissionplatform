<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\TemplateValues;
use org\ccextractor\submissionplatform\objects\NoticeType;
use Slim\App;
use Slim\Http\Request;

/**
 * Class BaseController provides some base methods that can be used by all Controllers that inherit from this class.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
abstract class BaseController implements IController
{
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
     * Adds the three necessary values to produce a x notice, based on the given NoticeType.
     *
     * @param App $app The Slim framework app.
     * @param NoticeType $type The type of notice to show.
     * @param string $message The message to display.
     */
    protected function setNoticeValues(App $app, NoticeType $type, $message){
        $app->templateValues->add("notice_message",$message);
        $status = "error";
        $icon = "remove";
        switch($type){
            case NoticeType::SUCCESS:
                $status = "success";
                $icon = "check";
                break;
            case NoticeType::WARNING:
                $status = "warning";
                $icon = "warning";
                break;
            case NoticeType::INFORMATION:
                $status = "information";
                $icon = "info-circle";
                break;
            case NoticeType::ERROR:
                // Error flows through to default, which is error.
            default:
                // Defaults are already set before switch.
                break;
        }
        $app->templateValues->add("notice_status",$status);
        $app->templateValues->add("notice_icon",$icon);
    }

    /**
     * Adds the two CSRF variables to the template values for rendering.
     *
     * @param App $app The Slim framework app.
     * @param Request $request The request object which contains the CSRF middleware.
     */
    protected function setCSRF(App $app,Request $request){
        $nameKey = $app->csrf->getTokenNameKey();
        $valueKey = $app->csrf->getTokenValueKey();
        // Register values for template rendering
        $app->templateValues->add("csrf_name_key",$nameKey);
        $app->templateValues->add("csrf_name_value", $request->getAttribute($nameKey));
        $app->templateValues->add("csrf_value_key",$valueKey);
        $app->templateValues->add("csrf_value_value", $request->getAttribute($valueKey));
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