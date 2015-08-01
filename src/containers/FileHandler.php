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
     * @var string
     */
    private $store_dir;

    /**
     * FileHandler constructor.
     *
     * @param DatabaseLayer $dba
     */
    public function __construct(DatabaseLayer $dba, $store_dir)
    {
        $this->dba = $dba;
        $this->store_dir = $store_dir;
        $this->forbiddenExtensions = $this->dba->getForbiddenExtensions();
    }

    public function process(User $user, SplFileInfo $file)
    {
        $extension = $file->getExtension();
        if(in_array($extension,$this->forbiddenExtensions)){
            // Delete file
            unlink($file->getPathname());
            // Store deletion message
            $this->dba->storeProcessMessage($user, "File ".$file->getFilename()." was removed due to an illegal extension.");
        } else {
            // Move file to processing folder
            rename($file->getPathname(),$this->store_dir.$file->getFilename());
            // Store in processing queue.
            $this->dba->storeQueue($user,$this->store_dir.$file->getFilename());
        }
    }


}