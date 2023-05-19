#!/bin/sh
# send email with alert to this address
ADMIN_SEND_EMAIL="work.andron@gmail.com shahram@uniques.ca bateni@uniques.ca"
# set alert level 90% is default
ALERT=90

DATE=`date "+%Y.%m.%d_%H_%M"`
TARGETDIR=/backup
TARGETDIRLIMITED=/backup/limited
TARGETDIRTIVOLI=/backupbytivoli
USER=user
PASS=pass

# Remove files 14 days old
find $TARGETDIR -mtime +14 -type f -exec rm {} \;


# Create AU/CA/Test AU/Test CA DBs backups
DATABASES="Officio_Australia_Main_Statistics Officio_Au_Main Officio_Main_Statistics Officio_Main Officio_Australia_Webmail Officio_Main_Webmail Officio_Au_Test_Statistics Officio_Au_Test Officio_Test"
for DATABASE in $DATABASES
do
    echo "Dumping database: $DATABASE"
    /usr/bin/mysqldump -q -u$USER -p$PASS --single-transaction --max_allowed_packet=512M $DATABASE | gzip -c > $TARGETDIR/$DATE.$DATABASE.gz
    cp $TARGETDIR/$DATE.$DATABASE.gz $TARGETDIRTIVOLI/$DATABASE.gz

    /usr/bin/mysqldump -u$USER -p$PASS -d -t --routines --triggers --max_allowed_packet=512M $DATABASE | gzip -c > $TARGETDIR/$DATE.$DATABASE.routines.gz
    cp $TARGETDIR/$DATE.$DATABASE.routines.gz $TARGETDIRTIVOLI/$DATABASE.routines.gz
done


# Remove files 29 days old
find $TARGETDIRLIMITED -mtime +29 -type f -exec rm {} \;

# Create limited DBs backups
LIMITEDDATABASES="Officio_Au_Main Officio_Main"
for LIMITEDDATABASE in $LIMITEDDATABASES
do
    echo "Dumping limited database: $LIMITEDDATABASE"
    /usr/bin/mysqldump -q -u$USER -p$PASS --single-transaction --max_allowed_packet=512M --ignore-table=$LIMITEDDATABASE.eml_messages $LIMITEDDATABASE | gzip -c > $TARGETDIRLIMITED/$DATE.$LIMITEDDATABASE-limited.gz
done


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
