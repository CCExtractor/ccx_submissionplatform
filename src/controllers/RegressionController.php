<?php
namespace org\ccextractor\submissionplatform\controllers;

use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\objects\NoticeType;
use org\ccextractor\submissionplatform\objects\RegressionCategory;
use org\ccextractor\submissionplatform\objects\RegressionTest;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

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

                        return $this->view->render($response, "regression/view.html.twig",
                            $this->templateValues->getValues()
                        );
                    }

                    // Return not found
                    return $this->view->render($response->withStatus(404), "regression/notfound.html.twig",
                        $this->templateValues->getValues()
                    );
                }
                )->setName($self->getPageName() . "_id");
                // GET/POST: delete regression test
                $this->map(['GET', 'POST'], '/delete', function ($request, $response, $args) use ($self) {
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
                // GET/POST: edit results
                $this->map(['GET', 'POST'], '/results', function ($request, $response, $args) use ($self) {
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
                )->setName($self->getPageName() . "_id_results");
            }
            );
            $this->get('/new', function ($request, $response, $args) use ($self) {
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
                    return $this->view->render($response->withStatus(404), "regression/category-notfound.html.twig",
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
                    return $this->view->render($response->withStatus(404), "regression/category-notfound.html.twig",
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