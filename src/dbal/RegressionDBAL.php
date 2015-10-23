<?php
namespace org\ccextractor\submissionplatform\dbal;

use org\ccextractor\submissionplatform\objects\RegressionCategory;
use org\ccextractor\submissionplatform\objects\RegressionInputType;
use org\ccextractor\submissionplatform\objects\RegressionOutputType;
use org\ccextractor\submissionplatform\objects\RegressionTest;
use org\ccextractor\submissionplatform\objects\RegressionTestResult;
use org\ccextractor\submissionplatform\objects\Sample;
use PDO;

/**
 * Class RegressionDBAL holds all database logic regarding regression tests.
 *
 * @package org\ccextractor\submissionplatform\dbal
 */
class RegressionDBAL extends  AbstractDBAL
{
    public function getAllRegressionTests()
    {
        $stmt = $this->pdo->prepare("
SELECT
	r.`id` AS 'regression_id', r.`command` AS 'regression_command', r.`input` AS 'regression_input', r.`output` AS 'regression_output',
	s.`id` AS 'sample_id', s.`hash` AS 'sample_hash', s.`extension` AS 'sample_extension',
	c.id AS 'category_id', c.name AS 'category_name', c.description AS 'category_description',
	o.`test_out_id` AS 'rt_id', o.`correct` AS 'rt_hash', o.`expected` AS 'rt_extra', o.`ignore` AS 'rt_ignore'
FROM regression_test r
	JOIN regression_test_out o ON r.`id` = o.`regression_id`
	JOIN sample s ON s.`id` = r.`sample_id`
	LEFT JOIN regression_test_category z ON z.regression_test_id = r.`id`
	LEFT JOIN category c ON c.id = z.category_id
ORDER BY r.`id`, o.`test_out_id` ASC;");
        $results = [];
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $id = -1;
            /** @var RegressionTest $test */
            $test = null;
            while ($data !== false) {
                if($id !== $data["regression_id"]){
                    if($test !== null){
                        $results[] = $test;
                    }
                    $test = new RegressionTest(
                        $data['regression_id'],
                        new Sample($data['sample_id'],$data['sample_hash'],$data['sample_extension']),
                        new RegressionCategory($data['category_id'],$data['category_name'],$data['category_description']),
                        $data['regression_command'],
                        RegressionInputType::createFromString($data['regression_input']),
                        RegressionOutputType::createFromString($data['regression_output'])
                    );
                    $id = $test->getId();
                }
                $test->addOutputFile(new RegressionTestResult(
                    $data['rt_id'],'',$data['rt_extra'],$data['rt_ignore'],''));
                $data = $stmt->fetch();
            }
            if($test !== null){
                $results[] = $test;
            }
        }
        return $results;
    }

    public function getRegressionCategories()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM category ORDER BY name ASC");
        $result = [];
        if($stmt->execute() && $stmt->rowCount() > 0){
            $data = $stmt->fetch();
            while($data !== false){
                $result[] = new RegressionCategory($data['id'],$data['name'],$data['description']);
                $data = $stmt->fetch();
            }
        }
        return $result;
    }

    public function getRegressionTest($id)
    {
        $stmt = $this->pdo->prepare("
SELECT
	r.`id` AS 'regression_id', r.`command` AS 'regression_command', r.`input` AS 'regression_input', r.`output` AS 'regression_output',
	s.`id` AS 'sample_id', s.`hash` AS 'sample_hash', s.`extension` AS 'sample_extension',
	c.id AS 'category_id', c.name AS 'category_name', c.description AS 'category_description',
	o.`test_out_id` AS 'rt_id', o.`correct` AS 'rt_hash', o.`expected` AS 'rt_extra', o.`ignore` AS 'rt_ignore'
FROM regression_test r
	JOIN regression_test_out o ON r.`id` = o.`regression_id`
	JOIN sample s ON s.`id` = r.`sample_id`
	LEFT JOIN regression_test_category z ON z.regression_test_id = r.`id`
	LEFT JOIN category c ON c.id = z.category_id
WHERE r.id = :id
ORDER BY r.`id`, o.`test_out_id` ASC;");
        $stmt->bindParam(":id",$id, PDO::PARAM_INT);
        $result = RegressionTest::getNullInstance();
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $data = $stmt->fetch();
            $id = -1;
            /** @var RegressionTest $test */
            $test = null;
            while ($data !== false) {
                if($id !== $data["regression_id"]){
                    $test = new RegressionTest(
                        $data['regression_id'],
                        new Sample($data['sample_id'],$data['sample_hash'],$data['sample_extension']),
                        new RegressionCategory($data['category_id'],$data['category_name'],$data['category_description']),
                        $data['regression_command'],
                        RegressionInputType::createFromString($data['regression_input']),
                        RegressionOutputType::createFromString($data['regression_output'])
                    );
                    $id = $test->getId();
                }
                $test->addOutputFile(new RegressionTestResult(
                    $data['rt_id'],$data['rt_hash'],$data['rt_extra'],$data['rt_ignore']));
                $data = $stmt->fetch();
            }
            if($test !== null){
                $result = $test;
            }
        }
        return $result;
    }
}