#!/bin/sh
# send email with alert to this address
ADMIN_SEND_EMAIL="work.andron@gmail.com shahram@uniques.ca bateni@uniques.ca"
# set alert level 90% is default
ALERT=90

DATE=`date "+%Y.%m.%d_%H_%M"`
TARGETDIR=/home/officio/secure/backup
FILENAME_DB=officio_db
FILENAME_SRC=officio_src
FILENAME_EMAIL=officio_email
FILENAME_EMAIL_DB=officio_email_db
FILENAME_DATA=officio_data
DATABASE=Officio_Main
DATABASE_EMAIL=Officio_Main_Webmail
USER=uniques_main
PASS=PMdYTenVZw8u5CD20YUK

# Remove files 100 days old
find $TARGETDIR -ctime +100 -type f -exec rm {} \;

# Create backups of DB, sources and company files
# These backup files will be deleted after they'll be copied to the cloud
mysqldump -q -u$USER -p$PASS $DATABASE | gzip -c > $TARGETDIR/$DATE.$FILENAME_DB.gz
if curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca:81/api/remote/backup/file/$DATE.$FILENAME_DB.gz | grep "^Backup done.$" > /dev/null; then
 rm $TARGETDIR/$DATE.$FILENAME_DB.gz
fi

mysqldump -q -u$USER -p$PASS $DATABASE_EMAIL | gzip -c > $TARGETDIR/$DATE.$FILENAME_EMAIL_DB.gz
if curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca:81/api/remote/backup/file/$DATE.$FILENAME_EMAIL_DB.gz | grep "^Backup done.$" > /dev/null; then
 rm $TARGETDIR/$DATE.$FILENAME_EMAIL_DB.gz
fi

tar -czf $TARGETDIR/$DATE.$FILENAME_SRC.tar.gz /home/officio/secure/application /home/officio/secure/library /home/officio/secure/public --exclude /home/officio/secure/public/email
if curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca:81/api/remote/backup/file/$DATE.$FILENAME_SRC.tar.gz | grep "^Backup done.$" > /dev/null; then
 rm $TARGETDIR/$DATE.$FILENAME_SRC.tar.gz
fi

# Check disk space usage
df -H | grep -vE '^Filesystem|tmpfs|cdrom|Mounted on' | awk '{ print $6 " " $5" "$1 }' | grep '^/ ' | while read output;

do
    usep=$(echo $output | awk '{ print $2}' | cut -d'%' -f1 )
    partition=$(echo $output | awk '{ print $3 }' )
    if [ $usep -ge $ALERT ]; then
        echo "Running out of space \"$partition ($usep%)\" on $(hostname) as on $(date)" |
        mail -s "Alert: Almost out of disk space ($usep% used)" $ADMIN_SEND_EMAIL
    fi
done
