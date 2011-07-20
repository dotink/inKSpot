#!/bin/bash

if [ `ps -A | grep php5-cgi | head 1` ]; then
	killall -9 php5-cgi
fi

if [ `ps -A | grep nginx | head 1` ]; then
	killall -9 nginx
fi

echo "DROP DATABASE inkspot" | sudo -u postgres psql
echo "DROP USER inkspot" | sudo -u postgres psql
echo "DROP USER inkspot_ro" | sudo -u postgres psql

rpl " pgsql" "" /etc/nsswitch.conf

rm -rf /home/inkspot
rm -rf /home/users
rm -rf /home/domains
