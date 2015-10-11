<?php
/**
 * Copy and rename this file to configuration.php and fill in the necessary values in order for the script to work
 */

// User agent, must be equal to the user agent in ccx_vmscripts/variables(-sample)
define("BOT_CCX_USER_AGENT","...");
// User agent for reply back, must be equal to the user agent in the command server settings
define("BOT_CCX_USER_AGENT_S","...");
// Path to the python vboxmanager script
define("BOT_CCX_VBOX_MANAGER","/path/to/run_vm.py");
// Path to the runNext script
define("BOT_CCX_WORKER","/path/to/runNext");
// The author of the application (don't forget the @ sign if you want to get a notification when someone uses the tool).
define("BOT_AUTHOR","@github_username");
// Path to the folder that holds the local clones of the repositories
define("BOT_REPOSITORY_FOLDER","/path/to/local/clones/collection/folder/");
// HMAC private key. This is used for the HMAC used during repository exchange with the worker.
define("BOT_HMAC_KEY","Random_characters_here");
// URL to the worker script for repository management
define("BOT_WORKER_URL","http://my.domain/subfolder/repository.php");