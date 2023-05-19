#!/bin/sh
# send email with alert to this address
ADMIN_SEND_EMAIL="work.andron@gmail.com shahram@uniques.ca bateni@uniques.ca"
# set alert level 90% is default
ALERT=90

DATE=`date "+%Y.%m.%d_%H_%M"`
TARGETDIR="/var/www/operations/backup"
DBHOST="192.168.1.22"
FILENAME_DB=timc_db
FILENAME_SRC=timc_src
FILENAME_DATA=timc_data
DATABASE=timc_main
USER=timc_backup
PASS=SECUREPASSWORD

# Remove files 14 days old
find $TARGETDIR -ctime +14 -type f -exec rm {} \;

#Create backups of DB, sources and company files
mysqldump -h$DBHOST -u$USER -p$PASS $DATABASE | gzip -c > $TARGETDIR/$DATE.$FILENAME_DB.gz
tar -czf $TARGETDIR/$DATE.$FILENAME_SRC.tar.gz /var/www/operations/application /var/www/operations/library /var/www/operations/public --exclude /var/www/operations/public/email
tar -czf $TARGETDIR/$DATE.$FILENAME_DATA.tar.gz /var/www/operations/data

# Check disk space usage
df -H | grep -vE '^Filesystem|tmpfs|cdrom|Mounted on' | awk '{ print $6 " " $5" "$1 }' | grep '^/ ' | while read output;

do
usep=$(echo $output | awk '{ print $2}' | cut -d'%' -f1 )
partition=$(echo $output | awk '{ print $3 }' )
if [ $usep -ge $ALERT ]; then
    echo "Running out of space \"$partition ($usep%)\" on $(hostname) as on $(date)" |
    mail -s "TIMC Alert: Almost out of disk space ($usep% used)" $ADMIN_SEND_EMAIL
fi
done
