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

    /**
     * Processes an uploaded file, creates the hash and adds it to the queue.
     *
     * @param User $user The user that uploaded the file.
     * @param SplFileInfo $file The file info.
     * @param string $original The original name. If left blank, the SplFileInfo name will be used.
     */
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

    /**
     * Removes an item from the queue and the disk.
     *
     * @param User $user The user that manages the queued item.
     * @param int $id The id of the queued item.
     * @return bool True if the file was removed and scrapped from the queue.
     */
    public function remove(User $user, $id){
        $filename = $this->dba->getQueueFilename($user,$id);
        if($filename !== false){
            if(unlink($this->temp_dir.$filename)){
                return $this->dba->removeQueue($id);
            }
        }
        return false;
    }

    /**
     * Saves a queued item as a real sample.
     *
     * @param User $user The user that uploaded the sample.
     * @param int $id The id of the queue item.
     * @param int $ccx_version_id The id of the used CCExtractor version.
     * @param string $platform The platform that was used.
     * @param string $params The used parameters.
     * @param string $notes Additional notes.
     * @return bool True if the queue item existed, the file was moved and stored in the database.
     */
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

    /**
     * Adds a file to an existing sample.
     *
     * @param User $user The user that uploaded the file & sample.
     * @param int $queue_id The id of the file in the queue that will be appended.
     * @param int $sample_id The id of the sample where the queued item will be linked to.
     * @return bool True if the queue item exists, the sample exists, the file was moved and this was registered in the database.
     */
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

    /**
     * Fetches the media info for a given sample.
     *
     * @param object $sample The sample.
     * @param bool|false $generate If true, it will generate the media info if it's missing.
     * @return bool|string The media info if it's loaded, false otherwise.
     */
    public function fetchMediaInfo($sample, $generate = false){
        // Media info, if existing, is stored in $store_dir/media/hash.xml
        $finfo = new SplFileInfo($this->store_dir."/media/".$sample["hash"].".xml");
        $media = false;
        if($finfo->isFile()){
            $media = $this->loadMediaInfo($finfo);
        } else if($generate){
            $media = $this->createMediaInfo($finfo, new SplFileInfo($this->store_dir."/".$sample["hash"]));
        }
        return $media;
    }

    private function loadMediaInfo(SplFileInfo $mediaInfo){
        // TODO: load xml, process it and return
        return true;
    }

    private function createMediaInfo(SplFileInfo $mediaInfo, SplFileInfo $sample){
        // TODO: run mediainfo on sample, store xml.

        return $this->loadMediaInfo($mediaInfo);
    }
}