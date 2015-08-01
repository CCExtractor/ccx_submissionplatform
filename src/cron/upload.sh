#!/bin/bash
#
# Small script to run after a file was uploaded
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

/usr/bin/php -f "$DIR/filechecker.php" "$1" >> "$DIR/log.txt" 2>&1