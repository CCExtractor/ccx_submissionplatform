<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\QueuedSample;
use org\ccextractor\submissionplatform\objects\Sample;
use org\ccextractor\submissionplatform\objects\SampleData;
use org\ccextractor\submissionplatform\objects\User;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SplFileInfo;
use XMLReader;

/**
 * Class FileHandler handles some file operations for samples.
 *
 * @package org\ccextractor\submissionplatform\containers
 */
class FileHandler implements ServiceProviderInterface
{
    /**
     * @var DatabaseLayer The layer that connects to the database.
     */
    private $dba;
    /**
     * @var array A list of forbidden extensions.
     */
    private $forbiddenExtensions;
    /**
     * @var string The directory that holds the queued items.
     */
    private $temp_dir;
    /**
     * @var string The directory that holds the submitted samples.
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
            $hash = hash_file('sha256',$file->getPathname());
            // FIXME: check if there's no samples (queued) yet with the same hash
            $ext = ($extension !== "")?".".$extension:"";
            $original_name = str_replace($ext,"",$fName);
            // Copy file to processing folder
            copy($file->getPathname(),$this->temp_dir.Sample::getFileName($hash,$extension));
            // Store in processing queue.
            $sample = new QueuedSample(-1,$hash,$extension,$original_name,$user);
            $this->dba->storeQueue($sample);
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
        $queued = $this->dba->getQueuedSample($user,$id);
        if($queued !== false){
            if(unlink($this->temp_dir.$queued->getSampleFileName())){
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
        $queued = $this->dba->getQueuedSample($user,$id);
        if($queued !== false){
            if(rename($this->temp_dir.$queued->getSampleFileName(),$this->store_dir.$queued->getSampleFileName())){
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
                if (rename($this->temp_dir . $queued->getSampleFileName(), $this->store_dir ."extra/". $sample->getAdditionalFileName($queued->getExtension()))) {
                    return $this->dba->moveQueueToAppend($queue_id, $sample_id);
                }
            }
        }
        return false;
    }

    /**
     * Fetches the media info for a given sample.
     *
     * @param SampleData $sample The sample.
     * @param bool $generate If true, it will generate the media info if it's missing.
     * @return bool|string The media info if it's loaded, false otherwise.
     */
    public function fetchMediaInfo(SampleData $sample, $generate = false){
        // Media info, if existing, is stored in $store_dir/media/hash.xml
        $finfo = new SplFileInfo($this->store_dir."media/".$sample->getHash().".xml");
        $media = false;
        if($finfo->isFile()){
            $media = $this->loadMediaInfo($finfo);
        } else if($generate){
            $media = $this->createMediaInfo($finfo, new SplFileInfo($this->store_dir.$sample->getHash()));
        }
        return $media;
    }

    /**
     * Loads and parses the media info from given file.
     *
     * @param SplFileInfo $mediaInfo The mediainfo xml containing the metadata.
     * @return bool|string False on failure, a string with the contents otherwise.
     */
    private function loadMediaInfo(SplFileInfo $mediaInfo){
        $reader = new XMLReader();
        if($reader->open($mediaInfo->getPathname())){
            return $reader->readOuterXml();
        }
        return false;
    }

    /**
     * Generates (and then loads) the media info for a given sample. Mediainfo output is stored in a given file.
     *
     * @param SplFileInfo $mediaInfo The mediainfo xml that will contain the metadata.
     * @param SplFileInfo $sample The sample that needs the media info.
     * @return bool|string False on failure, a string with the mediainfo contents otherwise.
     */
    private function createMediaInfo(SplFileInfo $mediaInfo, SplFileInfo $sample){
        $command = "mediainfo --Full --Output=XML ".escapeshellarg($sample->getPathname())." > ".escapeshellarg($mediaInfo->getPathname());
        shell_exec($command);
        return $this->loadMediaInfo($mediaInfo);
    }
}