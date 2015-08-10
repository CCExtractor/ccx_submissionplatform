<?php
/**
 * Created by PhpStorm.
 * User: Willem
 *
 * This file will be called by the FTP upload script after a file is uploaded, so it can be processed automatically.
 */
use org\ccextractor\submissionplatform\containers\DatabaseLayer;
use org\ccextractor\submissionplatform\containers\FileHandler;

include_once __DIR__.'/../configuration.php';
require __DIR__.'/../../vendor/autoload.php';

// Init DB connection
/** @var DatabaseLayer $dba */
$dba = new DatabaseLayer(DATABASE_SOURCE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD);
// File checker
/** @var FileHandler $checker */
$checker = new FileHandler($dba, TEMP_STORAGE, PERM_STORAGE);

// Get first argument, which is the file.
$filePath = $argv[1];
// Get SplFileInfo object of the given file.
$fi = new SplFileInfo($filePath);
$dir = $fi->getPathInfo();
$path_parts = explode("/",$dir->getPathname());

// Check the path, and extract necessary information
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