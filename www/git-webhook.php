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

if($json === null){
    http_response_code(500);
    exit;
}

// Now check if it's production :)

// TODO: finish