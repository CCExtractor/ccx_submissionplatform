<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */

namespace org\ccextractor\submissionplatform\objects;

/**
 * Class Test holds the data for a single test run.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class Test
{
    /**
     * @var int The id of the test.
     */
    private $id;
    /**
     * @var string The token that can be used to report progress.
     */
    private $token;
    /**
     * @var bool Is the test run finished?
     */
    private $finished;
    /**
     * @var string The repository where the test was run on
     */
    private $repository;
    /**
     * @var string The branch that was used for testing.
     */
    private $branch;
    /**
     * @var string The hash of the commit that was tested.
     */
    private $commit;
    /**
     * @var string The type that was tested (commit, pull request, ...).
     */
    private $type;
    /**
     * @var array A list of all the progress entries.
     */
    private $progress;

    /**
     * Test constructor.
     *
     * @param int $id The id of the test.
     * @param string $token The token that can be used to report progress.
     * @param bool $finished Is the test run finished?
     * @param string $repository The repository where the test was run on
     * @param string $branch The branch that was used for testing.
     * @param string $commit The hash of the commit that was tested.
     * @param string $type The type that was tested (commit, pull request, ...).
     * @param array $progress A list of all the progress entries.
     */
    public function __construct($id, $token, $finished, $repository, $branch, $commit, $type, array $progress = []){
        $this->id = $id;
        $this->token = $token;
        $this->finished = $finished;
        $this->repository = $repository;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->type = $type;
        $this->progress = $progress;
    }

    public static function getNullTest(){
        return new Test(-1,"",true,"","","","");
    }

    /**
     * @return int
     */
    public function getId(){
        return $this->id;
    }

    /**
     * @return string
     */
    public function getToken(){
        return $this->token;
    }

    /**
     * @return boolean
     */
    public function isFinished(){
        return $this->finished;
    }

    /**
     * @return string
     */
    public function getRepository(){
        return $this->repository;
    }

    /**
     * @return string
     */
    public function getBranch(){
        return $this->branch;
    }

    /**
     * @return string
     */
    public function getCommit(){
        return $this->commit;
    }

    /**
     * @return string
     */
    public function getType(){
        return $this->type;
    }

    /**
     * @return array
     */
    public function getProgress(){
        return $this->progress;
    }

    /**
     * Strips some parts from the git url to obtain a proper url
     * @return string
     */
    public function getRepositoryURL(){
        return str_replace(".git","",str_replace('git://','https://',$this->getRepository()));
    }

    /**
     * Strips some parts of the git url to return a proper repository name (owner/repository).
     * @return string
     */
    public function getCleanRepositoryName(){
        return str_replace(".git","",str_replace('git://github.com/','',$this->getRepository()));
    }
}