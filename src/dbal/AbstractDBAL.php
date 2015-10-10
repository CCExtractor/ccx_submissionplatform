<?php
namespace org\ccextractor\submissionplatform\dbal;

use PDO;

/**
 * Class AbstractDBAL is the abstract class that the other DBAL classes can use to inherit from.
 *
 * @package org\ccextractor\submissionplatform\dbal
 */
abstract class AbstractDBAL
{
    /**
     * @var PDO The connection to the database system.
     */
    protected $pdo;

    /**
     * AbstractDBAL constructor.
     *
     * @param PDO $pdo The connection to the database system.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}