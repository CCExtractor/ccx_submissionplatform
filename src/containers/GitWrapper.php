<?php
namespace org\ccextractor\submissionplatform\containers;

use Milo\Github\Api;

/**
 * Class GitWrapper Wrapper for the GitHub operations.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class GitWrapper extends Api
{
    /**
     * GitWrapper constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}