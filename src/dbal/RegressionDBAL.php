<?php
namespace org\ccextractor\submissionplatform\dbal;

use org\ccextractor\submissionplatform\objects\RegressionCategory;
use org\ccextractor\submissionplatform\objects\RegressionInputType;
use org\ccextractor\submissionplatform\objects\RegressionOutputType;
use org\ccextractor\submissionplatform\objects\RegressionTest;
use org\ccextractor\submissionplatform\objects\RegressionTestResult;
use org\ccextractor\submissionplatform\objects\Sample;
use PDO;
use PDOException;

/**
 * Class RegressionDBAL holds all database logic regarding regression tests.
 *
 * @package org\ccextractor\submissionplatform\dbal
 */
class RegressionDBAL extends AbstractDBAL
{
    public function getAllRegressionTests()
    {
        $stmt = $this->pdo->prepare("
SELECT
	r.id AS 'regression_id', r.command AS 'regression_command', r.input AS 'regression_input', r.output AS 'regression_output',
	s.id AS 'sample_id', s.hash AS 'sample_hash', s.extension AS 'sample_extension',
	c.id AS 'category_id', c.name AS 'category_name', c.description AS 'category_description',
	o.test_out_id AS 'rt_id', o.correct AS 'rt_correct', o.correct_extension AS 'rt_correct_extension', o.expected_filename AS 'rt_extra', o.ignore AS 'rt_ignore'
FROM regression_test r
	LEFT JOIN regression_test_out o ON r.id = o.regression_id
	JOIN sample s ON s.id = r.sample_id
	LEFT JOIN regression_test_category z ON z.regression_test_id = r.id
	LEFT JOIN category c ON c.id = z.category_id
ORDER BY r.id, o.test_out_id ASC;"
        );
        $results = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $id = -1;
            /** @var RegressionTest $test */
            $test = null;
            while ($data !== false) {
                if ($id !== $data["regression_id"]) {
                    if ($test !== null) {
                        $results[] = $test;
                    }
                    $test = new RegressionTest(
                        $data['regression_id'],
                        new Sample($data['sample_id'], $data['sample_hash'], $data['sample_extension']),
                        new RegressionCategory($data['category_id'], $data['category_name'],
                            $data['category_description']
                        ),
                        $data['regression_command'],
                        RegressionInputType::fromDatabaseString($data['regression_input']),
                        RegressionOutputType::fromDatabaseString($data['regression_output'])
                    );
                    $id = $test->getId();
                }
                if ($data['rt_id'] > 0) {
                    $test->addOutputFile(new RegressionTestResult(
                            $data['rt_id'], $data['rt_correct'], $data['rt_correct_extension'], $data['rt_extra'],
                            $data['rt_ignore']
                        )
                    );
                }
                $data = $stmt->fetch();
            }
            if ($test !== null) {
                $results[] = $test;
            }
        }

        return $results;
    }

    public function getRegressionCategories()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM category ORDER BY name ASC");
        $result = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            while ($data !== false) {
                $result[] = new RegressionCategory($data['id'], $data['name'], $data['description']);
                $data = $stmt->fetch();
            }
        }

        return $result;
    }

    public function getRegressionTest($id)
    {
        $stmt = $this->pdo->prepare("
SELECT
	r.id AS 'regression_id', r.command AS 'regression_command', r.input AS 'regression_input', r.output AS 'regression_output',
	s.id AS 'sample_id', s.hash AS 'sample_hash', s.extension AS 'sample_extension',
	c.id AS 'category_id', c.name AS 'category_name', c.description AS 'category_description',
	o.test_out_id AS 'rt_id', o.correct AS 'rt_correct', o.correct_extension AS 'rt_correct_extension', o.expected_filename AS 'rt_extra', o.ignore AS 'rt_ignore'
FROM regression_test r
	LEFT JOIN regression_test_out o ON r.id = o.regression_id
	JOIN sample s ON s.id = r.sample_id
	LEFT JOIN regression_test_category z ON z.regression_test_id = r.id
	LEFT JOIN category c ON c.id = z.category_id
WHERE r.id = :id
ORDER BY r.id, o.test_out_id ASC;"
        );
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = RegressionTest::getNullInstance();
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $id = -1;
            /** @var RegressionTest $test */
            $test = null;
            while ($data !== false) {
                if ($id !== $data["regression_id"]) {
                    $test = new RegressionTest(
                        $data['regression_id'],
                        new Sample($data['sample_id'], $data['sample_hash'], $data['sample_extension']),
                        new RegressionCategory($data['category_id'], $data['category_name'],
                            $data['category_description']
                        ),
                        $data['regression_command'],
                        RegressionInputType::fromDatabaseString($data['regression_input']),
                        RegressionOutputType::fromDatabaseString($data['regression_output'])
                    );
                    $id = $test->getId();
                }
                if ($data['rt_id'] > 0) {
                    $test->addOutputFile(new RegressionTestResult(
                            $data['rt_id'], $data['rt_correct'], $data['rt_correct_extension'], $data['rt_extra'],
                            $data['rt_ignore']
                        )
                    );
                }
                $data = $stmt->fetch();
            }
            if ($test !== null) {
                $result = $test;
            }
        }

        return $result;
    }

    public function addCategory($name, $description)
    {
        $stmt = $this->pdo->prepare("INSERT INTO category VALUES (NULL, :name, :description);");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":description", $description, PDO::PARAM_STR);

        return ($stmt->execute() && $stmt->rowCount() === 1);
    }

    /**
     * @param $id
     *
     * @return RegressionCategory
     */
    public function getCategory($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM category WHERE id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = RegressionCategory::getNullInstance();
        if ($stmt->execute() && $stmt->rowCount() == 1) {
            $data = $stmt->fetch();
            $result = new RegressionCategory($data["id"], $data["name"], $data["description"]);
        }

        return $result;
    }

    public function getRegressionTestsForCategory(RegressionCategory $category)
    {
        $id = $category->getId();
        $stmt = $this->pdo->prepare("
SELECT
	r.id AS 'regression_id', r.command AS 'regression_command', r.input AS 'regression_input', r.output AS 'regression_output',
	s.id AS 'sample_id', s.hash AS 'sample_hash', s.extension AS 'sample_extension',
	o.test_out_id AS 'rt_id', o.correct AS 'rt_correct', o.correct_extension AS 'rt_correct_extension', o.expected_filename AS 'rt_extra', o.ignore AS 'rt_ignore'
FROM regression_test r
	LEFT JOIN regression_test_out o ON r.id = o.regression_id
	JOIN sample s ON s.id = r.sample_id
	LEFT JOIN regression_test_category z ON z.regression_test_id = r.id
	LEFT JOIN category c ON c.id = z.category_id
WHERE c.id = :id
ORDER BY r.id, o.test_out_id ASC;"
        );
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $results = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $id = -1;
            /** @var RegressionTest $test */
            $test = null;
            while ($data !== false) {
                if ($id !== $data["regression_id"]) {
                    if ($test !== null) {
                        $results[] = $test;
                    }
                    $test = new RegressionTest(
                        $data['regression_id'],
                        new Sample($data['sample_id'], $data['sample_hash'], $data['sample_extension']),
                        $category,
                        $data['regression_command'],
                        RegressionInputType::fromDatabaseString($data['regression_input']),
                        RegressionOutputType::fromDatabaseString($data['regression_output'])
                    );
                    $id = $test->getId();
                }
                if ($data['rt_id'] > 0) {
                    $test->addOutputFile(new RegressionTestResult(
                            $data['rt_id'], $data['rt_correct'], $data['rt_correct_extension'], $data['rt_extra'],
                            $data['rt_ignore']
                        )
                    );
                }
                $data = $stmt->fetch();
            }
            if ($test !== null) {
                $results[] = $test;
            }
        }

        return $results;
    }

    public function deleteCategory(RegressionCategory $category)
    {
        $id = $category->getId();
        $stmt = $this->pdo->prepare("DELETE FROM category WHERE id = :id LIMIT 1");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function updateCategory(RegressionCategory $category)
    {
        $id = $category->getId();
        $name = $category->getName();
        $description = $category->getDescription();
        $stmt = $this->pdo->prepare("UPDATE category SET name = :name, description = :description WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":description", $description, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    /**
     * @param RegressionTest $test
     *
     * @return RegressionTest
     */
    public function addRegressionTest(RegressionTest $test)
    {
        $categoryId = $test->getCategory()->getId();
        $sampleId = $test->getSample()->getId();
        $command = $test->getCommand();
        $inputType = $test->getInput()->toDatabaseString();
        $outputType = $test->getOutput()->toDatabaseString();
        if ($this->pdo->beginTransaction()) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO regression_test VALUES (NULL, :sample, :command, :input, :output);"
                );
                $stmt->bindParam(":sample", $sampleId, PDO::PARAM_INT);
                $stmt->bindParam(":command", $command, PDO::PARAM_STR);
                $stmt->bindParam(":input", $inputType, PDO::PARAM_STR);
                $stmt->bindParam(":output", $outputType, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $id = $this->pdo->lastInsertId();
                    $stmt = $this->pdo->prepare("INSERT INTO regression_test_category VALUES (:category, :id);");
                    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                    $stmt->bindParam(":category", $categoryId, PDO::PARAM_INT);
                    $stmt->execute();
                    $this->pdo->commit();
                    $test->setId($id);
                }
            } catch (PDOException $p) {
                $this->pdo->rollBack();
            }
        }

        return $test;
    }

    public function getOutputTestResultById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM regression_test_out WHERE test_out_id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = null;
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            $data = $stmt->fetch();
            $result = new RegressionTestResult($id, $data["correct"], $data["correct_extension"],
                $data["expected_filename"], $data["ignore"]
            );
        }

        return $result;
    }

    public function addRegressionTestCorrectResult(
        RegressionTest $test,
        $correct,
        $correct_extension,
        $expected_filename,
        $ignore
    ) {
        $id = $test->getId();
        $ignore = (int) filter_var($ignore, FILTER_VALIDATE_BOOLEAN); // When MySQL table is set to tinyint, $ignore cannot be a bool value...
        $stmt = $this->pdo->prepare("INSERT INTO regression_test_out VALUES (NULL, :id, :correct, :correct_extension, :expected_filename, :ignore);"
        );
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":correct", $correct, PDO::PARAM_STR);
        $stmt->bindParam(":correct_extension", $correct_extension, PDO::PARAM_STR);
        $stmt->bindParam(":expected_filename", $expected_filename, PDO::PARAM_STR);
        $stmt->bindParam(":ignore", $ignore, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function deleteOutputTestResult(RegressionTestResult $result)
    {
        $id = $result->getId();
        $stmt = $this->pdo->prepare("DELETE FROM regression_test_out WHERE test_out_id = :id LIMIT 1;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function updateRegressionTestCorrectResult(RegressionTestResult $result, $newCorrect)
    {
        $id = $result->getId();
        $stmt = $this->pdo->prepare("UPDATE regression_test_out SET correct = :correct WHERE test_out_id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":correct", $newCorrect, PDO::PARAM_STR);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function editRegressionTestCorrectResult(
        RegressionTestResult $result,
        $correct_extension,
        $expected_filename,
        $ignore
    ) {
        $id = $result->getId();
        $ignore = (int) filter_var($ignore, FILTER_VALIDATE_BOOLEAN); // When MySQL table is set to tinyint, $ignore cannot be a bool value...
        $stmt = $this->pdo->prepare("UPDATE regression_test_out SET correct_extension = :extension, expected_filename = :expected, `ignore` = :ignore WHERE test_out_id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":extension", $correct_extension, PDO::PARAM_STR);
        $stmt->bindParam(":expected", $expected_filename, PDO::PARAM_STR);
        $stmt->bindParam(":ignore", $ignore, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() === 1;
    }

    public function deleteRegressionTest(RegressionTest $test)
    {
        $id = $test->getId();
        if($this->pdo->beginTransaction()){
            try {
                $stmt = $this->pdo->prepare("DELETE FROM regression_test_category WHERE regression_test_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM regression_test_out WHERE regression_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("DELETE FROM regression_test WHERE id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                return $this->pdo->commit();
            } catch(PDOException $e){
                $this->pdo->rollBack();
            }
        }
        return false;
    }

    public function updateRegressionTest(RegressionTest $test)
    {
        $id = $test->getId();
        $categoryId = $test->getCategory()->getId();
        $sampleId = $test->getSample()->getId();
        $command = $test->getCommand();
        $input = $test->getInput()->toDatabaseString();
        $output = $test->getOutput()->toDatabaseString();
        if($this->pdo->beginTransaction()){
            try {
                $stmt = $this->pdo->prepare("UPDATE regression_test_category SET category_id = :catId WHERE regression_test_id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":catId", $categoryId, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $this->pdo->prepare("UPDATE regression_test SET sample_id = :sampleId, command = :command, input = :input, output = :output WHERE id = :id;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":sampleId", $sampleId, PDO::PARAM_INT);
                $stmt->bindParam(":command", $command, PDO::PARAM_STR);
                $stmt->bindParam(":input", $input, PDO::PARAM_STR);
                $stmt->bindParam(":output", $output, PDO::PARAM_STR);
                $stmt->execute();
                return $this->pdo->commit();
            } catch(PDOException $e){
                $this->pdo->rollBack();
            }
        }
        return false;
    }
}