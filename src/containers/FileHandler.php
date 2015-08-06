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
    private $temp_dir;
    /**
     * @var string
     */
    private $store_dir;

    /**
     * FileHandler constructor
     *
     * @param DatabaseLayer $dba
     * @param $temp_dir
     * @param $store_dir
     */
    public function __construct(DatabaseLayer $dba, $temp_dir, $store_dir)
    {
        $this->dba = $dba;
        $this->temp_dir = $temp_dir;
        $this->store_dir = $store_dir;
        $this->forbiddenExtensions = $this->dba->getForbiddenExtensions();
    }

    public function process(User $user, SplFileInfo $file,$original="")
    {
        if($original !== ""){
            $fName = $original;
            $lastDot = strrpos($fName,".");
            $extension = ($lastDot !== false)?substr($fName,$lastDot+1):"";
        } else {
            $fName = $file->getFilename();
            $extension = $file->getExtension();
        }
        // FUTURE: more extensive mime-type checking?
        if(in_array($extension,$this->forbiddenExtensions)){
            // Store deletion message
            $this->dba->storeProcessMessage($user, "File ".$fName." was removed due to an illegal extension.");
        } else {
            // Get SHA256 of file
            $sha1 = hash_file('sha256',$file->getPathname());
            // FIXME: check if there's no samples (queued) yet with the same sha1
            $ext = ($extension !== "")?".".$extension:"";
            $original_name = str_replace($ext,"",$fName);
            // Copy file to processing folder
            copy($file->getPathname(),$this->temp_dir.$sha1.$ext);
            // Store in processing queue.
            $this->dba->storeQueue($user,$original_name,$sha1,$extension);
        }
        // Delete file
        unlink($file->getPathname());
    }

    public function remove(User $user, $id){
        $filename = $this->dba->getQueueFilename($user,$id);
        if($filename !== false){
            if(unlink($this->temp_dir.$filename)){
                return $this->dba->removeQueue($id);
            }
        }
        return false;
    }

    public function submitSample(User $user, $id, $ccx_version_id, $platform, $params, $notes){
        $sample = $this->dba->getQueuedSample($user,$id);
        if($sample !== false){
            $ext = ($sample["extension"] !== "")?".".$sample["extension"]:"";
            if(rename($this->temp_dir.$sample["hash"].$ext,$this->store_dir.$sample["hash"].$ext)){
                return $this->dba->moveQueueToSample($user, $id, $ccx_version_id, $platform, $params, $notes);
            }
        }
        return false;
    }

    public function appendSample(User $user, $queue_id, $sample_id){
        $queued = $this->dba->getQueuedSample($user,$queue_id);
        if($queued !== false){
            $sample = $this->dba->getSampleForUser($user, $sample_id);
            if($sample !== false) {
                $ext = ($queued["extension"] !== "") ? "." . $queued["extension"] : "";
                if (rename($this->temp_dir . $queued["hash"] . $ext, $this->store_dir ."extra/". $sample["hash"]."_".$sample["additional_files"] . $ext)) {
                    return $this->dba->moveQueueToAppend($queue_id, $sample_id);
                }
            }
        }
        return false;
    }

    public function fetchMediaInfo($sample, $generate=false){
        // TODO: fetch media info for sample, if not existing & $generate == true, create it
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