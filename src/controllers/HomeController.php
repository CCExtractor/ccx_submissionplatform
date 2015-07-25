<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\controllers;

use Milo\Github\Http\Response;
use Milo\Github\OAuth\Token;
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

    /**
     * @param App $app
     * @param array $base_values
     */
    public function register(App $app, array $base_values = [])
    {
        $base_values = $this->setDefaultBaseValues($base_values, $app);

        $app->get('/[home]',function($request, $response, $args) use($base_values){
            // Get latest GitHub commit
            // TODO: implement caching
            $token = new Token(BOT_TOKEN);
            $this->github->setToken($token);
            $ref = "heads/master";
            $commit = "unknown (error occurred)";
            /** @var Response $request */
            $request = $this->github->get(
                "/repos/:owner/:repo/git/refs/:ref",
                [
                    "owner" => REPOSITORY_OWNER,
                    "repo" => REPOSITORY_NAME,
                    "ref" => $ref
                ]
            );
            if($request->getCode() == Response::S200_OK){
                $json = json_decode($request->getContent());
                if($json !== null && isset($json->ref) && $json->ref == "refs/".$ref){
                    $commit = $json->object->sha;
                }
            }

            // Custom page values
            $base_values["ccx_last_release"] = $this->database->getLatestCCExtractorVersion();
            $base_values["ccx_latest_commit"] = $commit;

            return $this->view->render($response,'home.html.twig',$base_values);
        })->setName($this->getPageName());
    }
}