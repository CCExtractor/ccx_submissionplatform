<?php
namespace org\ccextractor\submissionplatform\objects;

/**
 * Class QueuedSample represents a sample that has been added to the queue, but is not yet finalized.
 *
 * @package org\ccextractor\submissionplatform\objects
 */
class QueuedSample extends Sample
{
    /**
     * @var User The user that submitted the sample for processing.
     */
    private $user;

    /**
     * QueuedSample constructor.
     *
     * @param int $id The id of this sample.
     * @param string $hash The hash of the sample.
     * @param string $extension The extension of the sample.
     * @param string $original_name The original name of the sample.
     * @param User $user The user that submitted this sample.
     */
    public function __construct($id, $hash, $extension, $original_name, User $user)
    {
        parent::__construct($id, $hash, $extension, $original_name);
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}