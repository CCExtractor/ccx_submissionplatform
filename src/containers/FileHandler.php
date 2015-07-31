<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\User;
use SplFileInfo;

class FileHandler
{
    /**
     * @var DatabaseLayer
     */
    private $dba;
    /**
     * @var array
     */
    private $forbiddenExtensions;

    /**
     * FileHandler constructor.
     *
     * @param DatabaseLayer $dba
     */
    public function __construct(DatabaseLayer $dba)
    {
        $this->dba = $dba;
        $this->forbiddenExtensions = $this->dba->getForbiddenExtensions();
    }

    public function process(User $user, SplFileInfo $file)
    {
        $extension = $file->getExtension();
        if(in_array($extension,$this->forbiddenExtensions)){
            // Delete file
            unlink();
            // Store deletion message
        } else {
            // Move file to processing folder

            // Store in processing queue.
        }
    }


}