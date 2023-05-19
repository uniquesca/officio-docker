#!/bin/bash

# These directories must be created and apache must have rwx access rights
directories=(\
    "/var/www/officio_au/checker/var/cache" \
    "/var/www/officio_au/checker/var/log" \
    "/var/www/officio_au/checker/var/tmp" \
)

# Create them and set access rights
for i in "${directories[@]}"
do
    test -d ${i} || mkdir -p ${i}
	setfacl -R -m u:apache:rwx ${i}
done

# Sync all files/folders except of some folders
rsync -avz --delete \
--exclude 'application/configs/*' \
--exclude 'public/cache/*' \
--exclude 'var/tmp/*' \
--exclude 'var/log/*' \
--exclude 'var/cache/*' \
/var/www/officio/checker/ /var/www/officio_au/checker