#!/bin/bash
# This script has to be executed being in project root folder.
# Script assumes that files' and directories' owner is user, and owner group is
# web-server, i.e. username:www-data or username:officio.
printf "Make sure proper owner and group are set up for the root Officio folder.\n"
printf "Owner should be a user responsible for deployment, and group should be web-server.\n"
printf "Example: ec2-user:apache, deployer:www-data\n"
chmod 775 backup -Rf
chmod 775 data -Rf
chmod 775 var/cache -Rf
chmod 775 var/log -Rf
chmod 775 var/pdf_tmp -Rf
chmod 775 var/tmp -Rf
chmod 775 public/captcha/images -Rf
chmod 775 public/website -Rf
chmod 775 public/email/data -Rf
chmod 775 public/help_files -Rf
chmod 775 public/cache -Rf
chmod +x scripts/convert_to_pdf.sh
chmod +x scripts/cron/cron-empty-tmp.sh
chmod +x library/PhantomJS/phantomjs

# Set "running permission" for specific files of the officio-pdftron module (if it is enabled)
if [ -d vendor/uniquesca/officio-pdftron ]; then
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/convert-pdf-to-xod.py
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/convert-pdf-to-xod.sh
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/get-fields-name.py
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/get-fields-name.sh
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/merge-pdf-xfdf.py
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/merge-pdf-xfdf.sh

    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python3/convert-pdf-to-xod.sh
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python3/get-fields-name.sh
    chmod +x vendor/uniquesca/officio-pdftron/library/pdftron-lib-python3/merge-pdf-xfdf.sh
fi
