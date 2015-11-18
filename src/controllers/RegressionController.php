<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\FileHandler;
use org\ccextractor\submissionplatform\objects\NoticeType;
use org\ccextractor\submissionplatform\objects\RegressionCategory;
use org\ccextractor\submissionplatform\objects\RegressionInputType;
use org\ccextractor\submissionplatform\objects\RegressionOutputType;
use org\ccextractor\submissionplatform\objects\RegressionTest;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use SplFileInfo;

/**
 * Class RegressionController holds the logic for regression tests.
 *
 * @package org\ccextractor\submissionplatform\controllers
 */
class RegressionController extends BaseController
{
    /**
     * TestController constructor
     */
    public function __construct()
    {
        parent::__construct("Regression tests");
    }

    /**
     * Registers the routes for this controller in the given app.
     *
     * @param App $app The instance of the Slim framework app.
     */
    function register(App $app)
    {
        $self = $this;
        $app->group("/regression", function () use ($self) {
            /** @var App $this */
            // GET: show start of controller
            $this->get('[/]', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                $this->templateValues->add("tests", $dba->getRegression()->getAllRegressionTests());
                $this->templateValues->add("categories", $dba->getRegression()->getRegressionCategories());

                return $this->view->render($response, "regression/index.html.twig", $this->templateValues->getValues());
            }
            )->setName($self->getPageName());
            // Group specific regression test
            $this->group('/{id:[0-9]+}', function () use ($self) {
                /** @var App $this */
                // Show regression test
                $this->get('[/view]', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    // Get specific regression test
                    /** @var RegressionTest $test */
                    $test = $dba->getRegression()->getRegressionTest($args["id"]);
                    if ($test->getId() > 0) {
                        $this->templateValues->add("test", $test);
                        $self->setCSRF($this, $request);

                        return $this->view->render($response, "regression/test-view.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/not-found.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_id");
                // GET/POST: delete regression test
                $this->map(['GET', 'POST'], '/delete', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->hasRole("Contributor")) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // Get specific regression test
                    /** @var RegressionTest $test */
                    $test = $dba->getRegression()->getRegressionTest($args["id"]);
                    if ($test->getId() > 0) {
                        $this->templateValues->add("test", $test);

                        // TODO: finish
                        return $this->view->render($response, "regression/test-delete.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/not-found.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_id_delete");
                // GET/POST: edit regression test
                $this->map(['GET', 'POST'], '/edit', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->hasRole("Contributor")) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // TODO: finish
                }
                )->setName($self->getPageName() . "_id_edit");
                // POST: edit/update/delete results
                $this->post('/results', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Request $request */
                    /** @var Response $response */
                    $self->setDefaultBaseValues($this);
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    if (!$this->account->getUser()->hasRole("Contributor")) {
                        return $response->withJson(["error" => "You cannot perform this action."])->withStatus(403);
                    }
                    $nameKey = $this->csrf->getTokenNameKey();
                    $valueKey = $this->csrf->getTokenValueKey();
                    $result = [
                        "error" => "CSRF failed",
                        "csrf_name_key" => $nameKey,
                        "csrf_name_value" => $request->getAttribute($nameKey),
                        "csrf_value_key" => $valueKey,
                        "csrf_value_value" => $request->getAttribute($valueKey)
                    ];
                    if($request->getAttribute('csrf_status',true) !== true){
                        return $response->withJson($result)->withStatus(400);
                    }
                    $result["error"] = "An unexpected error happened.";
                    /** @var RegressionTest $test */
                    $test = $dba->getRegression()->getRegressionTest($args["id"]);
                    if ($test->getId() === 0) {
                        $result["error"] = "Invalid regression test";
                        return $response->withJson($result)->withStatus(404);
                    }

                    // Process request
                    if(isset($_POST["action"])){
                        switch($_POST["action"]){
                            case "add":
                                // File, extra, extension, ignore
                                if(isset($_FILES["correct"]) && isset($_POST["extra"]) && isset($_POST["extension"]) && isset($_POST["ignore"])){
                                    // Perform some checks
                                    // Validate string lengths
                                    if(strlen($_POST["extra"]) === 0 || strlen($_POST["extension"]) === 0) {
                                        $result["error"] = "Values cannot be empty";
                                    } else {
                                        /** @var FileHandler $fh */
                                        $fh = $this->file_handler;
                                        $fileCheck = $fh->isValidCorrectFile($_FILES["correct"], $_POST["extension"]);
                                        if ($fileCheck["passed"]) {
                                            $movedFile = $fh->addCorrectResult($fileCheck["file"], $_POST["extension"]);
                                            if($movedFile !== null){
                                                if($dba->getRegression()->addRegressionTestCorrectResult($test,
                                                    $movedFile->getBasename($_POST["extension"]), $_POST["extension"],
                                                    $_POST["extra"], $_POST["ignore"])){
                                                    $result = ["status" => "Added"];
                                                } else {
                                                    $result["error"] = "Could not upload to the database";
                                                }
                                            } else {
                                                $result["error"] = "File could not be moved";
                                            }
                                        } else {
                                            $result["error"] = "File was not uploaded correctly: ".$fileCheck["file"];
                                        }
                                    }
                                } else {
                                    $result["error"] = "Not all values are filled in.";
                                }
                                break;
                            case "edit":
                                // Id, extra, extension, ignore
                                if(isset($_POST["id"]) && isset($_POST["extra"]) && isset($_POST["extension"]) && isset($_POST["ignore"])){
                                    // Validate string lengths
                                    if(strlen($_POST["extra"]) === 0 || strlen($_POST["extension"]) === 0){
                                        $result["error"] = "Values cannot be empty";
                                    } else {
                                        // Validate id
                                        $r = $dba->getRegression()->getOutputTestResultById($_POST["id"]);
                                        if($r !== null){
                                            if($dba->getRegression()->editRegressionTestCorrectResult($r,
                                                $_POST["extension"], $_POST["extra"], $_POST["ignore"]
                                            )){
                                                $result = ["status" => "Edited"];
                                            } else {
                                                $result["error"] = "Could not upload to the database";
                                            }
                                        } else {
                                            $result["error"] = "Invalid result id";
                                        }
                                    }
                                } else {
                                    $result["error"] = "Not all values are filled in.";
                                }
                                break;
                            case "update":
                                // Id, file
                                if(isset($_POST["id"]) && isset($_FILES["correct"])){
                                    // Validate id
                                    $r = $dba->getRegression()->getOutputTestResultById($_POST["id"]);
                                    if($r !== null){
                                        /** @var FileHandler $fh */
                                        $fh = $this->file_handler;
                                        $fileCheck = $fh->isValidCorrectFile($_FILES["correct"], $r->getCorrectExtension());
                                        if($fileCheck["passed"]){
                                            $movedFile = $fh->addCorrectResult($fileCheck["file"], $_POST["extension"]);
                                            if($movedFile !== null){
                                                if($dba->getRegression()->updateRegressionTestCorrectResult($r,
                                                    $movedFile->getBasename($r->getCorrectExtension()))){
                                                    $result = ["status" => "Updated"];
                                                } else {
                                                    $result["error"] = "Could not upload to the database";
                                                }
                                            } else {
                                                $result["error"] = "File could not be moved";
                                            }
                                        } else {
                                            $result["error"] = "File was not uploaded correctly: ".$fileCheck["file"];
                                        }
                                    } else {
                                        $result["error"] = "Invalid result id";
                                    }
                                } else {
                                    $result["error"] = "Not all values are filled in.";
                                }
                                break;
                            case "delete":
                                // Id
                                if(isset($_POST["id"])){
                                    // Validate id
                                    $result = $dba->getRegression()->getOutputTestResultById($_POST["id"]);
                                    if($result !== null){
                                        if($dba->getRegression()->deleteOutputTestResult($result)){
                                            $result = ["ok" => "Deleted"];
                                        } else {
                                            $result["error"] = "Could not delete from database";
                                        }
                                    }
                                } else {
                                    $result["error"] = "Not all values are filled in.";
                                }
                                break;
                            default:
                                $result["error"] = "Invalid action.";
                                break;
                        }
                    } else {
                        $result["error"] = "Invalid action.";
                    }
                    // Return result
                    $response = $response->withJson($result);
                    if(array_key_exists("error",$result)){
                        $response = $response->withStatus(400);
                    }
                    return $response;
                }
                )->setName($self->getPageName() . "_id_results");
            }
            );
            $this->map(['GET', 'POST'], '/new', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Request $request */
                /** @var Response $response */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                if (!$this->account->getUser()->hasRole("Contributor")) {
                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                if ($request->isPost()) {
                    if ($request->getAttribute('csrf_status', true) === true) {
                        // Process post request
                        if (isset($_POST["category"]) && isset($_POST["sample"]) && isset($_POST["command"]) &&
                            isset($_POST["input_type"]) && isset($_POST["output_type"])
                        ) {
                            // Validate data
                            $category = $dba->getRegression()->getCategory($_POST["category"]);
                            $errors = "";
                            if ($category->getId() === -1) {
                                $errors .= "Invalid category; ";
                            }
                            $sample = $dba->getSampleById($_POST["sample"]);
                            if ($sample === false) {
                                $errors .= "Invalid sample; ";
                            }
                            if(strlen($_POST["command"]) === 0){
                                $errors .= "Command empty";
                            }
                            $inputType = null;
                            if (!RegressionInputType::isValid($_POST["input_type"])) {
                                $errors .= "Invalid input type; ";
                            } else {
                                $inputType = new RegressionInputType($_POST["input_type"]);
                            }
                            $outputType = null;
                            if (!RegressionOutputType::isValid($_POST["output_type"])) {
                                $errors .= "Invalid output type; ";
                            } else {
                                $outputType = new RegressionOutputType($_POST["output_type"]);
                            }
                            if ($errors === "") {
                                // Save in database
                                $regression = $dba->getRegression()->addRegressionTest(
                                    new RegressionTest(-1, $sample, $category, $_POST["command"], $inputType,
                                        $outputType
                                    )
                                );
                                if ($regression->getId() > 0) {
                                    // Redirect to regression page
                                    return $response->withRedirect($this->router->pathFor($self->getPageName() . "_id",
                                        ["id" => $regression->getId()]
                                    )
                                    );
                                }
                                $self->setNoticeValues($this, NoticeType::getError(), "Could not save in the database");
                            } else {
                                $self->setNoticeValues($this, NoticeType::getError(), $errors);
                            }
                        } else {
                            $self->setNoticeValues($this, NoticeType::getError(), "Missing values");
                        }
                    } else {
                        $self->setNoticeValues($this, NoticeType::getError(), "CSRF fail");
                    }
                }
                // Add values
                $this->templateValues->add("categories", $dba->getRegression()->getRegressionCategories());
                $this->templateValues->add("samples", $dba->getAllSamples());
                $this->templateValues->add("input_types", RegressionInputType::getAll());
                $this->templateValues->add("output_types", RegressionOutputType::getAll());
                // Add post values, if defined
                foreach(["category", "sample", "command", "input_type", "output_type"] as $value){
                    $this->templateValues->add($value,isset($_POST[$value])?$_POST[$value]:"");
                }
                // CSRF
                $self->setCSRF($this, $request);

                // Render
                return $this->view->render($response, "regression/test-new.html.twig",
                    $this->templateValues->getValues()
                );
            }
            )->setName($self->getPageName() . "_new");
            $this->group('/category/{id:[0-9]+}', function () use ($self) {
                /** @var App $this */
                // GET/POST: delete category
                $this->map(['GET', 'POST'], '/delete', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // Get category
                    /** @var RegressionCategory $category */
                    $category = $dba->getRegression()->getCategory($args["id"]);
                    if ($category->getId() > 0) {
                        $this->templateValues->add("category", $category);
                        // Get amount of associated tests
                        $count = count($dba->getRegression()->getRegressionTestsForCategory($category));
                        if ($count === 0) {
                            if ($request->isPost()) {
                                // Process post request
                                if ($request->getAttribute('csrf_status', true) === true) {
                                    if (isset($_POST["confirm"])) {
                                        if ($dba->getRegression()->deleteCategory($category)) {
                                            $self->setNoticeValues($this, NoticeType::getSuccess(),
                                                $category->getName() . " was deleted"
                                            );

                                            return $this->view->render($response,
                                                "regression/category-deleted.html.twig",
                                                $this->templateValues->getValues()
                                            );
                                        }
                                        $self->setNoticeValues($this, NoticeType::getError(), "Failed to delete");
                                    } else {
                                        $self->setNoticeValues($this, NoticeType::getError(), "Please confirm or cancel"
                                        );
                                    }
                                } else {
                                    $self->setNoticeValues($this, NoticeType::getError(), "CSRF failed.");
                                }
                            }
                            // Set CSRF
                            $self->setCSRF($this, $request);

                            // Render
                            return $this->view->render($response, "regression/category-delete.html.twig",
                                $this->templateValues->getValues()
                            );
                        }
                        $this->templateValues->add("count", $count);

                        return $this->view->render($response, "regression/category-populated.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/category-not-found.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_category_delete");
                // GET/POST: edit category
                $this->map(['GET', 'POST'], '/edit', function ($request, $response, $args) use ($self) {
                    /** @var App $this */
                    /** @var Response $response */
                    /** @var Request $request */
                    /** @var DatabaseLayer $dba */
                    $dba = $this->database;
                    $self->setDefaultBaseValues($this);
                    if (!$this->account->getUser()->isAdmin()) {
                        return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                            $this->templateValues->getValues()
                        );
                    }
                    // Get category
                    /** @var RegressionCategory $category */
                    $category = $dba->getRegression()->getCategory($args["id"]);
                    if ($category->getId() > 0) {
                        $this->templateValues->add("category", $category);
                        if ($request->isPost()) {
                            // Process post request
                            if ($request->getAttribute('csrf_status', true) === true) {
                                if (isset($_POST["name"]) && isset($_POST["description"]) && strlen($_POST["name"]
                                    ) > 0 && strlen($_POST["description"]) > 0
                                ) {
                                    // Process request
                                    $category->setName($_POST["name"]);
                                    $category->setDescription($_POST["description"]);
                                    if ($dba->getRegression()->updateCategory($category)) {
                                        $self->setNoticeValues($this, NoticeType::getSuccess(), "Category updated");
                                    } else {
                                        $self->setNoticeValues($this, NoticeType::getError(),
                                            "Could not update the database"
                                        );
                                    }
                                } else {
                                    $self->setNoticeValues($this, NoticeType::getError(), "Value missing.");
                                }
                            } else {
                                $self->setNoticeValues($this, NoticeType::getError(), "CSRF failed.");
                            }
                        }
                        // Set CSRF
                        $self->setCSRF($this, $request);

                        // Render
                        return $this->view->render($response, "regression/category-edit.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/category-not-found.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_category_edit");
            }
            );
            // GET/POST: add category
            $this->map(['GET', 'POST'], '/new-category', function ($request, $response, $args) use ($self) {
                /** @var App $this */
                /** @var Response $response */
                /** @var Request $request */
                /** @var DatabaseLayer $dba */
                $dba = $this->database;
                $self->setDefaultBaseValues($this);
                if (!$this->account->getUser()->isAdmin()) {
                    return $this->view->render($response->withStatus(403), "forbidden.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                $fill = true;
                if ($request->isPost()) {
                    // Process post request
                    if ($request->getAttribute('csrf_status', true) === true) {
                        if (isset($_POST["name"]) && isset($_POST["description"]) && strlen($_POST["name"]
                            ) > 0 && strlen($_POST["description"]) > 0
                        ) {
                            // Process request
                            if ($dba->getRegression()->addCategory($_POST["name"], $_POST["description"])) {
                                $self->setNoticeValues($this, NoticeType::getSuccess(), "Category added");
                                $fill = false;
                            } else {
                                $self->setNoticeValues($this, NoticeType::getError(), "Could not add to database");
                            }
                        } else {
                            $self->setNoticeValues($this, NoticeType::getError(), "Value missing.");
                        }
                    } else {
                        $self->setNoticeValues($this, NoticeType::getError(), "CSRF failed.");
                    }
                }
                if ($fill) {
                    $this->templateValues->add("name", isset($_POST["name"]) ? $_POST["name"] : "");
                    $this->templateValues->add("description", isset($_POST["description"]) ? $_POST["description"] : ""
                    );
                }
                // Set CSRF values
                $self->setCSRF($this, $request);

                // Render
                return $this->view->render($response, "regression/category-add.html.twig",
                    $this->templateValues->getValues()
                );
            }
            )->setName($self->getPageName() . "_category_add");
        }
        );
    }
}