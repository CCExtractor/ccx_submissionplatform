<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

class SampleInfoController extends BaseController
{
    /**
     * SampleInfoController constructor.
     */
    public function __construct()
    {
        parent::__construct("Sample Info");
    }

    function register(App $app, array $base_values = [])
    {
        $base_values["pageName"] = $this->getPageName();
        $app->get('/sample-info',function($request, $response, $args) use($base_values){
            return $this->view->render($response,'sample-info.html.twig',$base_values);
        })->setName($this->getPageName());
    }
}