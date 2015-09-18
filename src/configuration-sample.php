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
// Amazon SES (Simple Email Service) credentials
define("AMAZON_SES_USER","Amazon_User_Id");
define("AMAZON_SES_PASS","Amazon_User_Password");
// Location to store media samples for temporary storage (for processing)
define("TEMP_STORAGE","/path/to/temporary/storage/");
// Location to store media samples after submitting
define("PERM_STORAGE","/path/to/permanent/storage/");
// Git Webhook secret id (verification purposes)
define("GIT_WEBHOOK","mysupersecretidhere");
// Git remote location, which will be used to clone the repository if it does not exists.
define("GIT_REMOTE","https://github.com/canihavesomecoffee/ccx_submissionplatform.git");
// Location of the Git repo. Should not be equal to the location that is served
define("GIT_LOCATION","/home/web-user/git_repo/");
// Real location of the site
define("GIT_DEPLOY","/var/www/site/");