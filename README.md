# CCExtractor Sample Submission Platform

This repository contains the code for a Sample Submission Platform, which allows for a unified place to report errors, 
submit samples, view existing samples and more. It was developed during GSoC 2015.

You can find a running version here: [CCExtractor Submission Platform](http://ccextractor.canihavesome.coffee)

## Concept

While CCExtractor is an awesome tool and it works flawlessly most of the time, bugs occur occasionally (as with all 
software that exists). These are usually reported through a variety of channels (private email, mailing list, GitHub, 
...).

The aim of this project is to build a platform, which is accessible to everyone (after sign up), that provides a single 
place to upload, view samples and associated test results.

It will be closely integrated with GitHub and the [GitHub bot](https://github.com/canihavesomecoffee/ccx_gitbot).

## Installation

### Requirements

* Nginx (Other possible when modifying the sample download section)
* PHP >= 5.5, with CURL
* MySQL
* Pure-FTPD with mysql
* Composer

### Database

Create a new database with the name of your wish, and then use the statements from the `installation.sql` file to 
complete the database, or run the `installation.sql` immediately for a database with preset name.

### Web platform

1. Download / clone the GitHub repository
2. Copy the src/configuration-sample.php to src/configuration.php and fill in the values
3. Install the Composer dependencies by running `composer update`

#### SplEnum PECL

If you don't have the SplTypes pear package installed yet, you can do this as follows:

1. (optional) Install php-dev (`sudo apt-get install php-dev` or `sudo apt-get install php5-dev`).
2. (optional) Install php-pear (`sudo apt-get install php-pear`).
3. Install SplEnum (`sudo pecl install SPL_Types`).
4. If the install succeeded, add the extension to your `php.ini` file: `extension=spl_types.so`.
5. Restart the PHP service.

### Nginx configuration for X-Accel-Redirect

To serve files without any PHP overhead, the X-Accel-Redirect feature of Nginx is used. To enable it, a special section 
(as seen below) needs to be added to the nginx configuration file:

```
location /protected/ {
    internal;
    alias /path/to/storage/of/samples/; # Trailing slash is important!
}
```

More info on this directive is available at the [Nginx wiki](http://wiki.nginx.org/NginxXSendfile).

Other web servers can be configured too (see this excellent [SO](http://stackoverflow.com/a/3731639) answer), but will 
require a small modification in the relevant section of the SampleInfoController that handles the download.

### File upload size for HTTP

There are a couple of places where you need to take care to set a big enough size (depending on your wishes) when you 
want to set/increase the upload limit for HTTP uploads.

#### Nginx

If the upload is too large, Nginx will throw a `413 Request entity too large`. This can be solved by adding

```
# Increase Nginx upload limit
client_max_body_size 1G;
```

And setting it to an appropriate limit.

#### PHP

The `php.ini` contains two places where it limits the file upload size:

1. post_max_size
2. upload_max_filesize

Set these to an appropriate value.

### Pure-FTPD configuration

To allow upload of big files, FTP can be used. Since the goal is to keep the uploaded files of the users anonymous for 
other users, every user should get it's own FTP account.

Since system accounts pose a possible security threat, virtual accounts using MySQL can be used instead (and it's easier
 to manage too).

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
# For now we use plaintext. While this is terribly insecure in case of a database leakage, it's not really an issue, 
# given the fact that the passwords for the FTP accounts will be randomly generated and hence do not contain sensitive 
# user info (we need to show the password on the site after all).
MYSQLCrypt      plaintext
# Queries
MYSQLGetPW      SELECT Password FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetUID     SELECT Uid FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetGID     SELECT Gid FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MYSQLGetDir     SELECT Dir FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
MySQLGetQTAFS   SELECT QuotaFiles FROM ftpd WHERE User="\L" AND status="1" AND (ipaccess = "*" OR ipaccess LIKE "\R")
# Override queries for UID & GID
MYSQLDefaultUID 2015 # Set the UID of the ftpuser here
MYSQLDefaultGID 2015 # Set the GID of the ftpgroup here
```

Create a file `/etc/pure-ftpd/conf/ChrootEveryone` with the following contents:

```
yes
```

And do the same for `/etc/pure-ftpd/conf/CreateHomeDir` and `/etc/pure-ftpd/conf/CallUploadScript`

Then modify the `/etc/default/pure-ftpd-common`, and configure the next values:

```
UPLOADSCRIPT=/path/to/cron/upload.sh
UPLOADUID=1234 # User that owns the upload.sh script
UPLOADGID=1234 # Group that owns the upload.sh script
```

If necessary, you can also set an appropriate value in the Umask file (`/etc/pure-ftpd/conf/Umask`).

After this you can restart Pure-FTPD with `sudo /etc/init.d/pure-ftpd-mysql restart`

Note: if you don't see a line saying:

`Restarting ftp upload handler: pure-uploadscript.`

You need to start the pure-uploadscript. This can be done as follows (where 1000 is replaced with the gid & uid 
specified above):

`sudo pure-uploadscript -u 1000 -g 1000 -B -r /home/path/to/src/cron/upload.sh`

You can also verify this by running `ps aux | grep pure-uploadscript`. If it still doesn't work, rebooting the server 
might help.

## Contributing

If you want to help this project forward, or have a solution for some of the issues or bugs, don't hesitate to help! 
You can fork the project, create a branch for the issue/problem/... and afterwards create a pull request for it.

It will be reviewed as soon as possible.

## Security

Security is taken seriously, but even though many precautions have been taken, bugs always can occur. If you discover 
any security related issues, please send an email to ccextractor@canihavesome.coffee (GPG key 
[0xF8643F5B](http://pgp.mit.edu/pks/lookup?op=vindex&search=0x3AFDC9BFF8643F5B), fingerprint 53FF DE55 6DFC 27C3 C688 
1A49 3AFD C9BF F864 3F5B) instead of using the issue tracker, in order to prevent abuse while it's being patched.