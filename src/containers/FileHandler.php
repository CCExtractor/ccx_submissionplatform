<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\User;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SplFileInfo;

class FileHandler implements ServiceProviderInterface
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
            // Store deletion message
            $this->dba->storeProcessMessage($user, "File ".$file->getFilename()." was removed due to an illegal extension.");
        } else {
            // Get SHA1 of file
            $sha1 = sha1_file($file->getPathname());
            $original_name = str_replace(".".$file->getExtension(),"",$file->getFilename());
            // Copy file to processing folder
            copy($file->getPathname(),$this->store_dir.$sha1.".".$file->getExtension());
            // Store in processing queue.
            $this->dba->storeQueue($user,$original_name,$sha1,$file->getExtension());
        }
        // Delete file
        unlink($file->getPathname());
    }

    public function remove(User $user, $id){
        $filename = $this->dba->getQueueFilename($user,$id);
        if($filename !== false){
            if(unlink($this->store_dir.$filename)){
                return $this->dba->removeQueue($id);
            }
        }
        return false;
    }

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple An Container instance
     */
    public function register(Container $pimple)
    {
        $pimple["file_handler"] = $this;
    }
}