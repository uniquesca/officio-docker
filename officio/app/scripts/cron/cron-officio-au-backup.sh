#!/bin/sh
# send email with alert to this address
ADMIN_SEND_EMAIL="work.andron@gmail.com shahram@uniques.ca bateni@uniques.ca"
# set alert level 90% is default
ALERT=90

DATE=`date "+%Y.%m.%d_%H_%M"`
TARGETDIR=/var/www/officio_au/secure/backup
FILENAME_DB=officio_au_db
FILENAME_SRC=officio_au_src
DATABASE=Officio_Au_Main
USER=uniques_au_main
PASS=UGAM3aBDQFzQWZyVQ38b

# Remove files 100 days old
find $TARGETDIR -ctime +100 -type f -exec rm {} \;

# Create backups of DB, sources and company files
mysqldump -q -u$USER -p$PASS $DATABASE | gzip -c > $TARGETDIR/$DATE.$FILENAME_DB.gz

tar -czf $TARGETDIR/$DATE.$FILENAME_SRC.tar.gz /var/www/officio_au/secure/application /var/www/officio_au/secure/library /var/www/officio_au/secure/public --exclude /var/www/officio_au/secure/public/email

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