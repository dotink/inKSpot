#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the installation as root"
	exit
fi

REQUIRED_BASE_SOFTWARE=(dialog rpl sudo rlwrap)

for i in ${REQUIRED_BASE_SOFTWARE[*]}; do
	if [ -z `which $i` ]; then
		apt-get -qq install $i;
	fi
done

dialog --yesno "Do you wish to update the system? (RECOMMENDED)" 5 80

if [ $? == 0 ]
	then
	echo "Updating..."
	apt-get -qq update
	apt-get -qq dist-upgrade
fi

echo "Installing base system software..."
apt-get -qq install postgresql libpam-pgsql libnss-pgsql2       # Database
apt-get -qq install php5 php5-cli php5-cgi php5-pgsql           # PHP Stuff
apt-get -qq install nginx spawn-fcgi mono-fastcgi-server        # WebServer Stuff
apt-get -qq install postfix postfix-pgsql                       # SMTP  Stuff
apt-get -qq install dovecot-common dovecot-imapd dovecot-pop3d  # POP/IMAP Stuff
apt-get -qq install spamassassin                                # Anti-Spam Stuff
apt-get -qq install clamsmtp clamav-freshclam                   # Anti-Virus Stuff

echo "Running freshclam for the first time..."
freshclam

echo "Adding 'inkspot' user and group to the system..."
addgroup --system inkspot
adduser  --system --ingroup inkspot inkspot

##
# Add inkspot user to root group and allow write to /home
##
adduser inkspot root
adduser www-data inkspot
adduser inkspot www-data
chmod 775 /home

##
# Create our bin directory
##
mkdir /home/inkspot/bin
cp -R bin/* /home/inkspot/bin
chown -R inkspot:inkspot /home/inkspot/bin

##
# Create our www directory
##
mkdir /home/inkspot/www
cp -R www/* /home/inkspot/www
chown -R inkspot:inkspot /home/inkspot/www

##
# Create our lib directory
##
cp -R lib/* /home/inkspot/lib
chown -R inkspot:inkspot /home/inkspot/lib
chmod -R 750 /home/inkspot

##
# Allow www-data to write to nginx configs
##
chgrp www-data /etc/nginx/sites-available
chgrp www-data /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-available

##
# Give inkspot a configuration home
##
mkdir /etc/inkspot
chown inkspot:inkspot /etc/inkspot

echo "Updating umask..."
echo 'session optional pam_umask.so umask=007' >> /etc/pam.d/common-session
umask 007

echo "Reconfiguring postgres authentication..."
for ver_dir in `ls -1 /etc/postgresql`; do
	cp etc/postgres/pg_hba.conf /etc/postgresql/$ver_dir/main/
	chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_hba.conf
	cp etc/postgres/pg_ident.conf /etc/postgresql/$ver_dir/main/
	chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_ident.conf
done
/etc/init.d/postgresql restart

password=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 16 | xargs`

echo "Setting up inKSpot database and permissions..."
echo "CREATE USER inkspot" | sudo -u postgres psql
echo "CREATE USER inkspot_ro PASSWORD '$password';" | sudo -u postgres psql
echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo -u postgres psql
psql -U inkspot < support/inkspot.sql

echo "Setting up PAM for PostgreSQL..."
cp etc/pam_pgsql.conf /etc/
chmod 644 /etc/pam_pgsql.conf
cp support/pam-configs/pgsql /usr/share/pam-configs/
pam-auth-update --package

echo "Setting up NSS for PostgreSQL..."
cp etc/nss-pgsql.conf /etc/
rpl -q \$\{password\} $password /etc/nss-pgsql.conf >/dev/null
chmod 644 /etc/nss-pgsql.conf

cp etc/nss-pgsql-root.conf /etc/
chmod 644 /etc/nss-pgsql-root.conf

cp etc/nsswitch.conf /etc/

echo "Adding inkspot hostname to /etc/hosts..."
echo "127.0.2.1 inkspot" >> /etc/hosts

echo "Setting up NGINX..."
cp etc/nginx/sites-available/inkspot /etc/nginx/sites-available/inkspot
ln -s /etc/nginx/sites-available/inkspot /etc/nginx/sites-enabled/inkspot
cp -R etc/inkspot/nginx /etc/inkspot/
chown inkspot:inkspot /etc/inkspot/nginx
/etc/init.d/nginx restart

echo "Setting up Spawn-FCGI environment..."
mkdir /home/inkspot/var
mkdir /home/inkspot/var/cgi
mkdir /home/inkspot/var/cgi/domains
mkdir /home/inkspot/var/cgi/users
chown -R inkspot:inkspot /home/inkspot/var
