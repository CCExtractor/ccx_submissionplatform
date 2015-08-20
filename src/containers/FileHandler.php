<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
namespace org\ccextractor\submissionplatform\containers;

use org\ccextractor\submissionplatform\objects\AdditionalFile;
use org\ccextractor\submissionplatform\objects\QueuedSample;
use org\ccextractor\submissionplatform\objects\Sample;
use org\ccextractor\submissionplatform\objects\User;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SimpleXMLElement;
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
     * @param DatabaseLayer $dba The layer that connects to the database.
     * @param string $temp_dir The directory that holds the queued items.
     * @param string $store_dir The directory that holds the submitted samples.
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
        if(in_array($extension,$this->forbiddenExtensions)){
            // Store deletion message
            $this->dba->storeProcessMessage($user, "File ".$fName." was removed due to an illegal extension.");
        } else {
            // Get SHA256 of file
            $hash = hash_file('sha256',$file->getPathname());
            if($this->dba->getSampleByHash($hash) === false && !$this->dba->queuedSampleExist($hash)){
                $ext = ($extension !== "") ? "." . $extension : "";
                $original_name = str_replace($ext, "", $fName);
                // Copy file to processing folder
                copy($file->getPathname(), $this->temp_dir . Sample::getFileName($hash, $extension));
                // Store in processing queue.
                $sample = new QueuedSample(-1, $hash, $extension, $original_name, $user);
                $this->dba->storeQueue($sample);
            } else {
                // Store deletion message
                $this->dba->storeProcessMessage($user, "File ".$fName." was removed because there is already a (queued) sample with the same hash submitted.");
            }
        }
        // Delete file
        unlink($file->getPathname());
    }

    /**
     * Removes an item from the queue and the disk.
     *
     * @param int $id The id of the queued item.
     * @param User $user The user that manages the queued item.
     * @return bool True if the file was removed and scrapped from the queue.
     */
    public function remove($id, User $user = null){
        $queued = $this->dba->getQueuedSample($id, $user);
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
        $queued = $this->dba->getQueuedSample($id, $user);
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
        $queued = $this->dba->getQueuedSample($queue_id, $user);
        if($queued !== false){
            $sample = $this->dba->getSampleForUser($user, $sample_id);
            if($sample !== false) {
                $additional = $this->dba->moveQueueToAppend(new AdditionalFile(-1,$sample,$queued->getOriginalName(),$queued->getExtension()),$queue_id);
                if($additional !== null){
                    return rename($this->temp_dir . $queued->getSampleFileName(), $this->store_dir ."extra/". $additional->getFileName());
                }
            }
        }
        return false;
    }

    /**
     * Fetches the media info for a given sample.
     *
     * @param Sample $sample The sample.
     * @param bool $generate If true, it will generate the media info if it's missing.
     * @param bool $filter If true, it will filter out the media info and return a formatted string. If false,
     * it'll return the full XML string.
     * @return bool|string The media info if it's loaded, false otherwise.
     */
    public function fetchMediaInfo(Sample $sample, $generate = false, $filter=true){
        // Media info, if existing, is stored in $store_dir/media/hash.xml
        $finfo = new SplFileInfo($this->store_dir."media/".$sample->getHash().".xml");
        $media = false;
        if($finfo->isFile()){
            $media = $this->loadMediaInfo($finfo,$filter);
        } else if($generate){
            $media = $this->createMediaInfo($finfo, new SplFileInfo($this->store_dir.$sample->getSampleFileName()), $filter);
        }
        return $media;
    }

    /**
     * Loads and parses the media info from given file.
     *
     * @param SplFileInfo $mediaInfo The mediainfo xml containing the metadata.
     * @param bool $filter If true, it will filter out the media info and return a formatted string. If false,
     * it'll return the full XML string.
     * @return bool|string|array False on failure, a string with the contents if no filter, or an array with key-value pairs to display.
     */
    private function loadMediaInfo(SplFileInfo $mediaInfo, $filter=true){
        $reader = new XMLReader();
        if($reader->open($mediaInfo->getPathname())){
            if($reader->read()){
                if(!$filter){
                    return file_get_contents($mediaInfo->getPathname());
                }
                // Build up information...
                $message = [];
                if($reader->name === "Mediainfo"){
                    $message["Media info version"] = $reader->getAttribute("version");
                    // SimpleXML is easier to process...
                    $node = new SimpleXMLElement($reader->readInnerXml());
                    // Fetch general information
                    $general_elems = $node->xpath("track[@type='General']");
                    if(sizeof($general_elems) === 1){
                        $general = $general_elems[0];
                        $message["General info"] = [
                            "Format" => (string)$general->Format,
                            "Codec id" => (string)$general->Codec_ID,
                            "File size" => (string)$general->File_size,
                            "Duration" => (string)$general->Duration
                        ];
                    } else {
                        $message["General info"] = "Could not fetch general info";
                    }
                    // Fetch video info
                    $video_elems = $node->xpath("track[@type='Video']");
                    if(sizeof($video_elems) >= 1){
                        $video = $video_elems[0];
                        $format_info = (string)$video->Format_Info;
                        $frame_mode = (string)$video->Frame_rate_mode;
                        $scan_order = (string)$video->Scan_order;
                        $message["Video info (first only)"] = [
                            "Format" => ((string)$video->Format).(($format_info !== "")?(" (".$format_info.")"):""),
                            "Duration" => (string)$video->Duration,
                            "Size" => ((string)$video->Width)." x ".((string)$video->Height),
                            "Display ratio" => (string)$video->Display_aspect_ratio,
                            "Frame rate" => ((string)$video->Frame_rate).(($frame_mode !== "")?(" (mode: ".$frame_mode.")"):""),
                            "Scan type" => ((string)$video->Scan_type).(($scan_order !== "")?(" (".$scan_order.")"):""),
                            "Writing library" => (string)$video->Writing_library
                        ];
                    } else {
                        $message["Video info (first only)"] = "No video info found";
                    }
                    // Fetch subtitles info
                    $text_elems = $node->xpath("track[@type='Text']");
                    if(sizeof($text_elems) >= 1){
                        $message["Text info"] = [];
                        foreach($text_elems as $text){
                            $message["Text info"]["ID ".((string)$text->ID)] = [
                                "Menu ID"     => (string)$text->Menu_ID,
                                "Format"      => (string)$text->Format,
                                "Muxing mode" => (string)$text->Muxing_mode
                            ];
                        }
                    } else {
                        $message["Text info"] = "No text info found";
                    }
                }
                return $message;
            }
        }
        return false;
    }

    /**
     * Generates (and then loads) the media info for a given sample. Mediainfo output is stored in a given file.
     *
     * @param SplFileInfo $mediaInfo The mediainfo xml that will contain the metadata.
     * @param SplFileInfo $sample The sample that needs the media info.
     * @param bool $filter If true, it will filter out the media info and return a formatted string. If false,
     * it'll return the full XML string.
     * @return bool|string False on failure, a string with the mediainfo contents otherwise.
     */
    private function createMediaInfo(SplFileInfo $mediaInfo, SplFileInfo $sample, $filter=true){
        $command = "mediainfo --Output=XML ".escapeshellarg($sample->getPathname())." > ".escapeshellarg($mediaInfo->getPathname());
        shell_exec($command);
        // Strip path info
        $xml = new SimpleXMLElement($mediaInfo->getRealPath(),0,true);
        foreach($xml->xpath("//track[@type='General']") as $node){
            $node->Complete_name = str_replace($this->store_dir,"",(string)$node->Complete_name);
        }
        $xml->asXML($mediaInfo->getRealPath());

        return $this->loadMediaInfo($mediaInfo, $filter);
    }

    /**
     * Returns a file size limit in bytes based on the PHP upload_max_filesize and post_max_size. Borrowed from Drupal.
     *
     * @return string The max size limit, formatted.
     */
    function file_upload_max_size() {
        static $max_size = -1;

        if ($max_size < 0) {
            // Start with post_max_size.
            $max_size = $this->parse_size(ini_get('post_max_size'));

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = $this->parse_size(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }
        return $this->formatBytes($max_size);
    }

    /**
     * Parses a given size. Borrowed from Drupal.
     *
     * @param string $size The size to parse into bytes.
     * @return float The amount of bytes that represents the passed size.
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        else {
            return round($size);
        }
    }

    /**
     * Function to format a given number of bytes in KB and up.
     *
     * @param float $bytes The amount of bytes to format.
     * @param int $precision The precision of the formatting.
     * @return string The formatted bytes.
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Deletes a sample and the associated files from the disk & database.
     *
     * @param Sample $sample The sample to delete
     * @return bool True on success, false on failure.
     */
    public function deleteSample(Sample $sample){
        // Unlink additional files
        $additional = $this->dba->getAdditionalFiles($sample);
        if(sizeof($additional) > 0){
            /** @var AdditionalFile $extra */
            foreach($additional as $extra){
                if($this->dba->removeAdditionalFile($extra)){
                    @unlink($this->store_dir . "extra/" . $extra->getFileName());
                }
            }
        }
        // Unlink media info
        $finfo = new SplFileInfo($this->store_dir."media/".$sample->getHash().".xml");
        if($finfo->isFile()){
            @unlink($finfo->getRealPath());
        }
        // Unlink sample itself
        if($this->dba->removeSample($sample->getId())){
            // Remove from DB
            return @unlink($this->store_dir.$sample->getSampleFileName());
        }
        return false;
    }

    /**
     * Deletes a given additional file from the disk.
     *
     * @param AdditionalFile $additional
     * @return bool True on success, false on failure.
     */
    public function deleteAdditionalFile(AdditionalFile $additional){
        if($this->dba->removeAdditionalFile($additional)){
            return unlink($this->store_dir."extra/".$additional->getFileName());
        }
    }
}