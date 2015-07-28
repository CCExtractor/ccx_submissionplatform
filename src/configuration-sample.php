<?php
/**
 * Created by PhpStorm.
 * User: Willem
 *
 * Copy and rename this file to configuration.php and fill in the necessary values in order for the script to work
 */

// MySQL database source name
define("DATABASE_SOURCE_NAME","mysql:dbname=MYDATABASENAME;host=localhost");
// MySQL username
define("DATABASE_USERNAME","my_username");
// MySQL password
define("DATABASE_PASSWORD","my_password");
// The token used by the bot to connect to the GitHub API
define("BOT_TOKEN","my_token_here");
// The owner of the repository
define("REPOSITORY_OWNER","owner_or_organisation_here");
// The name of the repository
define("REPOSITORY_NAME","my_repo_name_here");
// HMAC private key. This is used for the HMAC used during password reset procedure.
define("HMAC_KEY","a_random_string_of_characters");