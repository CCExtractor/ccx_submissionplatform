<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

interface IController
{
    function getPageName();
    function getPageDescription();
    function register(App $app, array $base_values=[]);
}