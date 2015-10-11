<?php
namespace org\ccextractor\submissionplatform\objects;

class RegressionTestResult
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $correctHash;
    /**
     * @var string
     */
    private $expectedExtra;
    /**
     * @var bool
     */
    private $ignoreResult;
    /**
     * @var string
     */
    private $resultHash;

    /**
     * RegressionTestResult constructor.
     *
     * @param int $id
     * @param string $correctHash
     * @param string $expectedExtra
     * @param bool $ignoreResult
     * @param string $resultHash
     */
    public function __construct($id, $correctHash, $expectedExtra, $ignoreResult, $resultHash = "")
    {
        $this->id = $id;
        $this->correctHash = $correctHash;
        $this->expectedExtra = $expectedExtra;
        $this->ignoreResult = $ignoreResult;
        $this->resultHash = $resultHash;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCorrectHash()
    {
        return $this->correctHash;
    }

    /**
     * @return string
     */
    public function getExpectedExtra()
    {
        return $this->expectedExtra;
    }

    /**
     * @return boolean
     */
    public function isIgnoreResult()
    {
        return $this->ignoreResult;
    }

    /**
     * @return string
     */
    public function getResultHash()
    {
        return $this->resultHash;
    }
}