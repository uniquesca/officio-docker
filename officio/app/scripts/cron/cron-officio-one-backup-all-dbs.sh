#!/bin/sh
# send email with alert to this address
ADMIN_SEND_EMAIL="work.andron@gmail.com shahram@uniques.ca bateni@uniques.ca"
# set alert level 90% is default
ALERT=90

DATE=`date "+%Y.%m.%d_%H_%M"`
TARGETDIR=/backup
TARGETDIRLIMITED=/backup/limited
USER=USER
PASS="PASS"

# Remove files 14 days old
find $TARGETDIR -mtime +14 -type f -exec rm {} \;


# Create ALL main DBs backups
DATABASES="officio officio_statistics"
for DATABASE in $DATABASES
do
    echo "Dumping database: $DATABASE"
    /usr/bin/mysqldump -q -u$USER -p$PASS --routines --single-transaction --max_allowed_packet=512M $DATABASE | gzip -c > $TARGETDIR/$DATE.db.$DATABASE.gz
done


# Remove files 29 days old
find $TARGETDIRLIMITED -mtime +29 -type f -exec rm {} \;

# Create limited DBs backups
LIMITEDDATABASES="officio"
for LIMITEDDATABASE in $LIMITEDDATABASES
do
    echo "Dumping limited database: $LIMITEDDATABASE"
    /usr/bin/mysqldump -q -u$USER -p$PASS --routines --single-transaction --max_allowed_packet=512M --ignore-table=$LIMITEDDATABASE.eml_messages $LIMITEDDATABASE | gzip -c > $TARGETDIRLIMITED/$DATE.db.$LIMITEDDATABASE-limited.gz
done

echo "Creating sources and companies data backups"
# create sources backup only once a week
if [[ $(date +%u) -gt 6 ]] ; then
    tar -czf $TARGETDIR/$DATE.sources.tar.gz /var/www/officio/secure/application /var/www/officio/secure/library /var/www/officio/secure/public --exclude /var/www/officio/secure/public/email
fi

tar -czf $TARGETDIR/$DATE.data.tar.gz /var/www/officio/secure/data --exclude /var/www/officio/secure/data/pdf --exclude /var/www/officio/secure/data/xod

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
