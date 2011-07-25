#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the removal as root"
	exit
fi

echo "Killing all active php5 instances..."
if [ "`ps -A | grep php5-cgi | head -1`" ]; then
	killall -9 php5-cgi
fi

echo "Removing NGINX config and restarting..."
rm /etc/nginx/sites-enabled/inkspot
rm /etc/nginx/sites-available/inkspot
if [ "`ps -A | grep nginx | head -1`" ]; then
	/etc/init.d/nginx restart
fi

echo "Removing PowerDNS config and restarting..."
rm /etc/powerdns/pdns.d/inkspot.conf
/etc/init.d/pdns restart

echo "Removing pgsql from nsswitch.conf..."
rpl -q " pgsql" "" /etc/nsswitch.conf

echo "Remvoing PAM module..."
pam-auth-update --package --remove pgsql
rm /etc/pam_pgsql.conf

echo "Removing database and database users..."
echo "DROP DATABASE inkspot" | sudo -u postgres psql
echo "DROP USER inkspot"     | sudo -u postgres psql
echo "DROP USER inkspot_ro"  | sudo -u postgres psql
echo "DROP USER inkspot_dns" | sudo -u postgres psql

echo "Removing system user..."
userdel inkspot
groupdel inkspot

echo "Removing inKSpot installed files..."
rm -rf /home/inkspot

echo "Removing inkSpot user and comain homes..."
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
