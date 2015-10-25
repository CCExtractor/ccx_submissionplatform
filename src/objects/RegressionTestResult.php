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
    private $correctExtension;
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
     * @param string $correctExtension
     * @param string $expectedExtra
     * @param bool $ignoreResult
     * @param string $resultHash
     */
    public function __construct($id, $correctHash, $correctExtension, $expectedExtra, $ignoreResult, $resultHash = "")
    {
        $this->id = $id;
        $this->correctHash = $correctHash;
        $this->correctExtension = $correctExtension;
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
    public function getCorrectExtension()
    {
        return $this->correctExtension;
    }

    public function getCorrectFilename(){
        return $this->getCorrectHash().$this->getCorrectExtension();
    }

    /**
     * @return string
     */
    public function getExpectedExtra()
    {
        return $this->expectedExtra;
    }

    /**
     * @param Sample $sample
     *
     * @return string
     */
    public function getExpectedFilename(Sample $sample){
        return $sample->getHash().$this->getExpectedExtra().$this->getCorrectExtension();
    }

    /**
     * @return boolean
     */
    public function IgnoreResult()
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