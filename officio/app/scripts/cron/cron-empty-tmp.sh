#!/bin/sh

### NOTE: don't move this file to some directory!
### It must be located in .../secure/scripts/cron directory

# Absolute path to this script, e.g. /home/user/bin/foo.sh
SCRIPT=$(readlink -f $0)

# Absolute path this script is in, thus /home/user/bin
SCRIPT_PATH=$(dirname ${SCRIPT})

# Officio path is located 2 levels up
OFFICIO_DIR=$(readlink -f ${SCRIPT_PATH}/../../);

# Delete temp files created by Apache (PHP) - e.g. during file uploading
find /tmp -ctime +2 -type f -group apache -exec rm {} \;

# Delete temp files created when communicating with Amazon S3 and other temp locations
find ${OFFICIO_DIR}/var/tmp -ctime +2 -type f -exec rm {} \;
find ${OFFICIO_DIR}/var/tmp/uploads -ctime +2 -type f -exec rm {} \;
find ${OFFICIO_DIR}/var/pdf_tmp -ctime +2 -type f -exec rm {} \;
find ${OFFICIO_DIR}/public/captcha/images -ctime +2 -type f -exec rm {} \;