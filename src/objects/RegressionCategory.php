<?php
namespace org\ccextractor\submissionplatform\objects;

class RegressionCategory
{
    /**
     * @var RegressionCategory
     */
    private static $nullInstance = null;

    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $description;

    /**
     * RegressionCategory constructor.
     *
     * @param int $id
     * @param string $name
     * @param string $description
     */
    public function __construct($id, $name, $description)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * @return RegressionCategory
     */
    public static function getNullInstance()
    {
        if (self::$nullInstance === null) {
            self::$nullInstance = new RegressionCategory(-1, "", "");
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }
}