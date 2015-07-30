<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Slim\App;

class UploadController extends BaseController
{
    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct("Upload","Upload samples to the repository.");
    }

    function register(App $app)
    {
        // TODO: Implement register() method.
    }
}