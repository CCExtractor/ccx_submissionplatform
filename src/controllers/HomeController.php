<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

class HomeController extends BaseController
{
    /**
     * HomeController constructor.
     */
    public function __construct()
    {
        parent::__construct("Home","Homepage of the 2015 GSoC project for CCExtractor for a sample submission platform.");
    }

    public function register(App $app, array $base_values = [])
    {
        $base_values["pageName"] = $this->getPageName();
        $base_values["pageDescription"] = $this->getPageDescription();

        // Custom page values
        $base_values["ccx_last_release"] = "0.78"; // TODO: pull in from DB or someplace else?
        $base_values["ccx_latest_commit"] = "f3654174fc568deb31e9bf4df1a55fef58cc7b67"; // TODO: pull in through GH api

        $app->get('/[home]',function($request, $response, $args) use($base_values){
            return $this->view->render($response,'home.html.twig',$base_values);
        })->setName($this->getPageName());
    }
}