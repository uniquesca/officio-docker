#!/bin/bash

# These directories must be created and apache must have rwx access rights
directories=(\
    "/var/www/officio_au/secure/backup" \
    "/var/www/officio_au/secure/data" \
    "/var/www/officio_au/secure/var/cache" \
    "/var/www/officio_au/secure/var/log" \
    "/var/www/officio_au/secure/var/log/payment" \
    "/var/www/officio_au/secure/var/log/xfdf" \
    "/var/www/officio_au/secure/var/pdf_tmp" \
    "/var/www/officio_au/secure/var/tmp" \
    "/var/www/officio_au/secure/var/tmp/lock" \
    "/var/www/officio_au/secure/var/tmp/uploads" \
    "/var/www/officio_au/secure/public/captcha/images" \
    "/var/www/officio_au/secure/public/website" \
    "/var/www/officio_au/secure/public/email/data" \
    "/var/www/officio_au/secure/public/help_files" \
    "/var/www/officio_au/secure/public/pdf" \
)

# Create them and set access rights
for i in "${directories[@]}"
do
    test -d ${i} || mkdir -p ${i}
	setfacl -R -m u:apache:rwx ${i}
done

# Sync all files/folders except of some folders
rsync -avz --delete \
--exclude 'data/*' \
--exclude 'application/config/*' \
--exclude 'application/cron/sync-au.sh' \
--exclude 'application/cron/cron-officio-au-backup.sh' \
--exclude 'tests/*' \
--exclude 'backup/*' \
--exclude 'public/captcha/images/*' \
--exclude 'public/help_files/*' \
--exclude 'public/pdf/*' \
--exclude 'public/website/*' \
--exclude 'public/email/data/settings/adminpanel.xml' \
--exclude 'public/email/data/settings/settings.xml' \
--exclude 'public/email/configUrls.php' \
--exclude 'var/tmp/*' \
--exclude 'var/log/*' \
--exclude 'var/cache/*' \
--exclude 'var/pdf_tmp/*' \
/var/www/officio/test/ /var/www/officio_au/secure

# Sync additional files/folders skipped in previous sync command
rsync -avz --delete /var/www/officio/test/data/blank/ /var/www/officio_au/secure/data/blank
#rsync -avz --delete /var/www/officio/test/data/pdf/ /var/www/officio_au/secure/data/pdf