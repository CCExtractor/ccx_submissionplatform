<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

/**
 * Interface IController represents an Controller interface.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
interface IController
{
    /**
     * Returns the name of the controller/page.
     *
     * @return string The name of the page
     */
    function getPageName();
    /**
     * Gets the page description.
     *
     * @return string The description of the page.
     */
    function getPageDescription();
    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app);
}