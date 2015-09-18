<?php
/**
 * Created by PhpStorm.
 * User: Willem
 *
 * This page receives Git(Hub) Webhook requests and processes them in order to automatically update the platform with the latest production branch code.
 */
include_once "../src/configuration.php";

// Do some preliminary checks. These won't hold back persons that read this file though ;)
if(strpos($_SERVER["HTTP_USER_AGENT"],"GitHub-Hookshot/") !== 0 || !isset($_SERVER["HTTP_X_GITHUB_EVENT"]) ||
    !isset($_SERVER["HTTP_X_GITHUB_DELIVERY"]) || !isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) ||
    !isset($_SERVER["CONTENT_TYPE"]) || $_SERVER["CONTENT_TYPE"] !== "application/json"){
    http_response_code(418);
    exit;
}

// Split signature in algorithm & signature
list ($hash_algorithm, $gitHubSignature) = explode("=", $_SERVER["HTTP_X_HUB_SIGNATURE"]);
// Get JSON payload from the input
$payload = file_get_contents('php://input');
// Validate the sent payload before we process it any further
$signature = hash_hmac($hash_algorithm, $payload, GIT_WEBHOOK);

if($signature !== $gitHubSignature){
    http_response_code(400);
    exit;
}

// Decode JSON
$json = json_decode($payload,true);

if($json === null || !isset($json["ref"])){
    http_response_code(500);
    echo "Invalid JSON";
    exit;
}

// Now check if it's production :)
if($json["ref"] !== "refs/heads/production"){
    echo "Not the production branch; ignoring";
    exit;
}

// debug file name
$debug_file = 'src/templates/built_with.html.twig';
// Full path to git binary is required if git is not in your PHP user's path. Otherwise just use 'git'.
$git_bin_path = 'git';

$logMessage = "[" . date('m/d/Y h:i:s a') . "][Updater] Starting update\n";
// Check if repository directory exists
if(!is_dir(GIT_LOCATION)){
    // Init git repo
    $logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] Need to clone the repository first\n";
    exec($git_bin_path. ' clone ' . GIT_REMOTE . ' ' . GIT_LOCATION . ' && cd ' . GIT_LOCATION);
} else {
    // Change directory and make a fetch
    $logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] Pull updates for repository\n";
    exec('cd ' . GIT_LOCATION . ' && ' . $git_bin_path . ' pull');
}

$logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] Going into maintenance mode\n";
// Put maintenance mode on, by copying the maintenance file
exec('cp ' . GIT_DEPLOY . '/www/maintenance.html.tpl ' . GIT_DEPLOY . '/www/maintenance.html');
// Do a git checkout to the web root
exec('cd ' . GIT_LOCATION . ' && GIT_WORK_TREE=' . GIT_DEPLOY . ' ' . $git_bin_path . ' checkout -f production');
// Get commit hash
$commit_hash = shell_exec('cd ' . GIT_LOCATION . ' && ' . $git_bin_path . ' rev-parse --short HEAD');
// Append commit hash to the template
file_put_contents(GIT_DEPLOY . $debug_file, '{% set build_commit = "' . $commit_hash . '" %}', FILE_APPEND);
// Run composer
$composer_output = shell_exec('cd ' . GIT_DEPLOY . ' && php composer.phar self-update && php composer.phar update');
file_put_contents('composer.log', $composer_output, FILE_APPEND);
$logMessage .= "[".date('m/d/Y h:i:s a')."][Updater] Updated composer files\n";
// Reset twig cache
exec('rm -rf ' . GIT_DEPLOY . '/twig_cache');
$logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] Reset of twig cache\n";
// Log the deployment
$logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] Deployed branch with commit: ";
$logMessage .= $commit_hash;
// Remove maintenance mode
exec('rm ' . GIT_DEPLOY . '/www/maintenance.html');
$logMessage .= "[" . date('m/d/Y h:i:s a') . "][Updater] end of maintenance mode\n\n";
file_put_contents('../deploy.log', $logMessage, FILE_APPEND);

echo "OK";