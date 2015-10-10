<?php
namespace org\ccextractor\submissionplatform\dba;

use PDO;

abstract class AbstractTables
{
    /**
     * @var PDO The connection to the database system.
     */
    protected $pdo;

    /**
     * AbstractTables constructor.
     *
     * @param PDO $pdo The connection to the database system.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}