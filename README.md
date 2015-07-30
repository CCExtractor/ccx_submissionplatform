# CCExctractor Sample Submission Platform

This repository contains the code for a Sample Submission Platform, which allows for a unified place to report errors, submit samples, view existing samples and more. It was developed during GSoC 2015.

You can find a running version here: [CCExtractor Submission Platform](http://ccextractor.canihavesome.coffee)

## Concept

While CCExtractor is an awesome tool and it works flawlessly most of the time, bugs occur occasionally (as with all software that exists). These are usually reported through a variety of channels (private email, mailing list, GitHub, ...).

The aim of this project is to build a platform, which is accessible to everyone (after sign up), that provides a single place to upload, view samples and associated test results.

It will be closely integrated with GitHub and the [GitHub bot](https://github.com/wforums/ccx_gitbot).

## Installation

### Requirements

* PHP >= 5.4 (5.5+ preferred)
* MySQL
* Pure-FTPD with mysql
* Composer

### Database

To be completed

### Web platform

1. Download / clone the GitHub repository
2. Copy the src/configuration-sample.php to src/configuration.php and fill in the values
3. Install the Composer dependencies by running `composer update`

### Pure-FTPD configuration

To allow upload of big files, FTP can be used. Since the goal is to keep the uploaded files of the users anonymous for other users, every user should get it's own FTP account.

Since system accounts pose a possible security threat, virtual accounts using MySQL can be used instead (and it's easier to manage too).

#### Pure-FTPD installation

`sudo apt-get install pure-ftpd-mysql`

If requested, answer the following questions as follows:

```
Run pure-ftpd from inetd or as a standalone server? <-- standalone
Do you want pure-ftpwho to be installed setuid root? <-- No
```

#### Special group & user creation

All MySQL users will be mapped to this user. Pick a group and user id that is still free

```
sudo groupadd -g 2015 ftpgroup
sudo useradd -u 2015 -s /bin/false -d /bin/null -c "pureftpd user" -g ftpgroup ftpuser
```

#### Configure Pure-FTPD

Edit the `/etc/pure-ftpd/db/mysql.conf` file (in case of Debian/Ubuntu) so it matches the next configuration:

```
MYSQLSocket      /var/run/mysqld/mysqld.sock
# user from the DATABASE_USERNAME in the configuration, or a separate one
MYSQLUser       user 
# password from the DATABASE_PASSWORD in the configuration, or a separate one
MYSQLPassword   ftpdpass
# The database name configured in the DATABASE_SOURCE_NAME dsn string in the configuration
MYSQLDatabase   pureftpd
# For now we use plaintext. While this is terribly insecure in case of a database leakage, it's not really an issue, given the fact that the passwords for the FTP accounts will be randomly generated and hence do not contain sensitive user info (we need to show the password on the site after all).
MYSQLCrypt      plaintext
# Queries
MYSQLGetPW      SELECT Password FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetUID     SELECT Uid FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetGID     SELECT Gid FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetDir     SELECT Dir FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MySQLGetBandwidthUL SELECT ULBandwidth FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MySQLGetBandwidthDL SELECT DLBandwidth FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MySQLGetQTASZ   SELECT QuotaSize FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MySQLGetQTAFS   SELECT QuotaFiles FROM ftpd WHERE User="\L"AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
# Override queries for UID & GID
MYSQLDefaultUID 2015 # Set the UID of the ftpuser here
MYSQLDefaultGID 2015 # Set the GID of the ftpgroup here
```

Create a file `/etc/pure-ftpd/conf/ChrootEveryone` with the following contents:

```
yes
```

And do the same for `/etc/pure-ftpd/conf/CreateHomeDir`.

After this you can restart Pure-FTPD with `sudo /etc/init.d/pure-ftpd-mysql restart`

## Contributing

If you want to help this project forward, or have a solution for some of the issues or bugs, don't hesitate to help! You can fork the project, create a branch for the issue/problem/... and afterwards create a pull request for it.

It will be reviewed as soon as possible.

## Security

If you discover any security related issues, please email ccextractor@canihavesome.coffee instead of using the issue tracker.