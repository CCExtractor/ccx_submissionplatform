<?php
namespace org\ccextractor\submissionplatform\objects;

/**
 * Class RegressionTest
 * @package org\ccextractor\submissionplatform\objects
 */
class RegressionTest
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var Sample
     */
    private $sample;
    /**
     * @var RegressionCategory
     */
    private $category;
    /**
     * @var string
     */
    private $command;
    /**
     * @var RegressionInputType
     */
    private $input;
    /**
     * @var RegressionOutputType
     */
    private $output;
    /**
     * @var array
     */
    private $outputFiles;

    /**
     * @var RegressionTest
     */
    private static $nullInstance = null;

    /**
     * RegressionTest constructor.
     *
     * @param int $id
     * @param Sample $sample
     * @param RegressionCategory $category
     * @param string $command
     * @param RegressionInputType $input
     * @param RegressionOutputType $output
     * @param array $outputFiles
     */
    public function __construct(
        $id,
        Sample $sample,
        RegressionCategory $category,
        $command,
        RegressionInputType $input,
        RegressionOutputType $output,
        array $outputFiles = []
    ) {
        $this->id = $id;
        $this->sample = $sample;
        $this->category = $category;
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
        $this->outputFiles = $outputFiles;
    }

    /**
     * @return RegressionTest
     */
    public static function getNullInstance()
    {
        if (self::$nullInstance === null) {
            self::$nullInstance = new RegressionTest(
                -1, Sample::getNullInstance(), RegressionCategory::getNullInstance(), '',
                RegressionInputType::fromDatabaseString(), RegressionOutputType::fromDatabaseString()
            );
        }

        return self::$nullInstance;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Sample
     */
    public function getSample()
    {
        return $this->sample;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return RegressionInputType
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return RegressionOutputType
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return array
     */
    public function getOutputFiles()
    {
        return $this->outputFiles;
    }

    /**
     * @return RegressionCategory
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param RegressionTestResult $result
     */
    public function addOutputFile(RegressionTestResult $result)
    {
        $this->outputFiles[] = $result;
    }

    /**
     * @return bool
     */
    public function didPass()
    {
        $pass = true;
        /** @var RegressionTestResult $outputFile */
        foreach ($this->outputFiles as $outputFile) {
            if ($outputFile->getResultHash() !== "") {
                $pass = false;
                break;
            }
        }

        return $pass;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}