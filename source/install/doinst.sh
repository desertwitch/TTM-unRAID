#!/bin/bash
#
# Copyright Derek Macias (parts of code from NUT package)
# Copyright macester (parts of code from NUT package)
# Copyright gfjardim (parts of code from NUT package)
# Copyright SimonF (parts of code from NUT package)
# Copyright Lime Technology (any and all other parts of Unraid)
#
# Copyright desertwitch (as author and maintainer of this file)
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License 2
# as published by the Free Software Foundation.
#
# The above copyright notice and this permission notice shall be
# included in all copies or substantial portions of the Software.
#
BOOT="/boot/config/plugins/dwttm"
DOCROOT="/usr/local/emhttp/plugins/dwttm"

chmod 755 /usr/bin/ttmd
chmod 755 /etc/rc.d/rc.ttmd
chmod 755 $DOCROOT/scripts/*
chmod 755 $DOCROOT/event/*
chmod 644 /etc/logrotate.d/ttmd

cp -n $DOCROOT/default.cfg $BOOT/dwttm.cfg >/dev/null 2>&1

# set up plugin-specific polling tasks
rm -f /etc/cron.daily/dwttm-poller >/dev/null 2>&1
ln -sf /usr/local/emhttp/plugins/dwttm/scripts/poller /etc/cron.daily/dwttm-poller >/dev/null 2>&1
chmod +x /etc/cron.daily/dwttm-poller >/dev/null 2>&1
