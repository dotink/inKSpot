#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the removal as root"
	exit
fi

echo "Killing all active php5 instances..."
if [ "`ps -A | grep php5-cgi | head -1`" ]; then
	service php5-fpm stop
fi

echo "Removing NGINX config and restarting..."
rm /etc/nginx/sites-enabled/inkspot
rm /etc/nginx/sites-available/inkspot
if [ "`ps -A | grep nginx | head -1`" ]; then
	service nginx stop
fi

echo "Removing pgsql from nsswitch.conf..."
rpl -q " pgsql" "" /etc/nsswitch.conf

echo "Removing PAM module..."
pam-auth-update --package --remove pgsql
rm /etc/pam_pgsql.conf

if [ -e /etc/inkspot/master_host ]; then
	echo "Removing PowerDNS config and restarting..."
	rm /etc/powerdns/pdns.d/inkspot.conf
	service pdns restart

	echo "Removing database and database users..."
	echo "DROP DATABASE inkspot" | sudo -u postgres psql
	echo "DROP USER inkspot"     | sudo -u postgres psql
	echo "DROP USER inkspot_ro"  | sudo -u postgres psql
	echo "DROP USER inkspot_dns" | sudo -u postgres psql

fi

echo "Removing inkspot system user..."
userdel inkspot
groupdel inkspot

echo "Removing inKSpot installed files..."
rm -rf /home/inkspot

echo "Removing inkSpot user and domain homes..."
for file in `find /home/users -name .immutable`; do
	chattr -i $file
done
rm -rf /home/users
for file in `find /home/domains -name .immutable`; do
	chattr -i $file
done
rm -rf /home/domains

echo "Deleting configuration files..."
rm -rf /etc/inkspot
