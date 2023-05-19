#!/bin/sh

s3cmd --no-check-md5 --config="/var/www/officio/secure/application/config/peer1_cloud.s3cfg" sync /backup/ "s3://dailybackups/db_backups/One/"