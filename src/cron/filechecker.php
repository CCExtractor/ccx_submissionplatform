<?php
/**
 * Created by PhpStorm.
 * User: Willem
 */
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\FileHandler;

include_once __DIR__.'/../configuration.php';
require __DIR__.'/../../vendor/autoload.php';

class FileNameFilter extends RecursiveRegexIterator {
    /**
     * (PHP 5 &gt;= 5.2.0)<br/>
     * Get accept status
     * @link http://php.net/manual/en/regexiterator.accept.php
     * @return bool true if a match, false otherwise.
     */
    public function accept()
    {
        $pass = ($this->isFile() && $this->getFilename() !== ".ftpquota") ||
            (! $this->isFile() && ($this->getFilename() !== "." && $this->getFilename() !== ".."));
        return $pass;
    }
}

function fetchAllRelevantFiles($folder) {
    $dir = new RecursiveDirectoryIterator($folder);
    $filter = new FileNameFilter($dir,'/.*/');
    $files = new RecursiveIteratorIterator($filter);
    $fileList = [];
    foreach($files as $file) {
        $fileList[] = $file;
    }
    return $fileList;
}

// Init DB connection
/** @var DatabaseLayer $dba */
$dba = new DatabaseLayer(DATABASE_SOURCE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD);
// File checker
/** @var FileHandler $checker */
$checker = new FileHandler($dba);

$filePath = $argv[1];
$fi = new SplFileInfo($filePath);
$dir = $fi->getPathInfo();
$path_parts = explode("/",$dir->getPathname());

if(sizeof($path_parts) >= 3 && $path_parts[0] === "" && $path_parts[1] === "home"){
    // Third part is the user dir
    $user_dir = $path_parts[2];
    if(is_numeric($user_dir)){
        // Verify user
        $user = $dba->getUserWithId($user_dir);
        if($user->getId() === intval($user_dir)){
            // Process file
            echo "Processing file ".$fi->getPathname()."\n";
            $checker->process($user,$fi);
        } else {
            echo "Skipping file ".$fi->getPathname()."; could not find associated user\n";
        }
    } else {
        echo "Skipping file " . $fi->getPathname() . "; directory non-numeric\n";
    }
} else {
    echo "Skipping file ".$fi->getPathname()."; not enough parts\n";
}