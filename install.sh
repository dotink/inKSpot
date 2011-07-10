#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the installation as root"
	exit
fi

if [ -z `which dialog` ]
	then
	echo "Installing 'dialog'..."
	apt-get -qq install dialog
fi

if [ -z `which rpl` ]
	then
	echo "Installing 'rpl'..."
	apt-get -qq install rpl
fi

if [ -z `which sudo` ]
	then
	echo "Installing 'sudo'..."
	apt-get -qq install sudo
fi

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
apt-get -qq install postfix-tls postfix-pgsql                   # SMTP  Stuff
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
useradd -G root inkspot
chmod 775 /home

##
# Create our bin directory
##
mkdir /home/inkspot/bin
cp -R bin/* /home/inkspot/bin
chown -R inkspot:inkspot /home/inkspot/bin
chmod -R 755 /home/inkspot/bin

##
# Create our www directory
##
mkdir /home/inkspot/www
chown -R inkspot:inkspot /home/inkspot/www
chmod 750 /home/inkspot/www

echo "Updating umask..."
echo 'session optional pam_umask.so umask=007' >> /etc/pam.d/common-session
umask 007

echo "Reconfiguring postgres authentication..."
for ver_dir in `ls -1 /etc/postgres`; do
	cp etc/postgres/pg_hba.conf /etc/postgres/$ver_dir/main/
	chown postgres:postgres /etc/postgres/$ver_dir/main/pg_hba.conf
	cp etc/postgres/pg_ident.conf /etc/postgres/$ver_dir/main/
	chown postgres:postgres /etc/postgres/$ver_dir/main/pg_ident.conf
done
/etc/init.d/postgresql restart

echo "Setting up inKSpot database and permissions..."
echo "CREATE USER inkspot;" | sudo -u postgres psql
echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo -u postgres psql
psql -U inkspot < support/inkspot.sql

echo "Setting up PAM for PostgreSQL..."
cp etc/pam_pgsql.conf /etc/
cp support/pam-configs/pgsql /usr/share/pam-configs/
pam-auth-update --package

echo "Setting up NSS for PostgreSQL..."
cp etc/nss-pgsql.conf /etc/
cp etc/nss-pgsql-root.conf /etc/
cp etc/nsswitch.conf /etc/
