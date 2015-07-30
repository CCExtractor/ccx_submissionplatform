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
    public function register(App $app)
    {
        $self = $this;
        $app->get('/[home]',function($request, $response, $args) use ($self) {
            $self->setDefaultBaseValues($this);
            // Get latest GitHub commit
            // FIXME: implement caching
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

            $this->templateValues->add("ccx_last_release", $this->database->getLatestCCExtractorVersion());
            $this->templateValues->add("ccx_latest_commit", $commit);

            return $this->view->render($response,'home/home.html.twig',$this->templateValues->getValues());
        })->setName($this->getPageName());
    }
}