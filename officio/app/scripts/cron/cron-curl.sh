#!/bin/sh
# $Id: cron-curl.sh,v 1.1 2011/07/05

# Run hourly - 22:01, 23:01...
1 * * * * /usr/bin/curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca/default/index/cron?p=0

# Run daily - 00:01
1 0 * * * /usr/bin/curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca/default/index/cron?p=1

# Run weekly - 00:01 each monday
1 0 * * 1 /usr/bin/curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca/default/index/cron?p=2

# Run daily - 00:01
1 0 * * * /usr/bin/curl --silent --compressed --user-agent Officio_Cron http://secure.officio.ca/api/index/run-recurring-payments
