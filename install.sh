#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the installation as root"
	exit
fi

if [ -z `which dialog` ]
	then
	echo "Installing 'dialog'..."
	apt-get -q install dialog
fi

if [ -z `which rpl` ]
	then
	echo "Installing 'rpl'..."
	apt-get -q install rpl
fi

if [ -z `which sudo` ]
	then
	echo "Installing 'sudo'..."
	apt-get -q install sudo
fi

dialog --yesno "Do you wish to update the system? (RECOMMENDED)" 5 80

if [ $? == 0 ]
	then
	echo "Updating..."
	apt-get -q update
	apt-get -q dist-upgrade
fi

echo "Adding 'inkspot' user and group to the system..."
addgroup --system inkspot
adduser --system --ingroup inkspot inkspot
mkdir /home/inkspot/www
chown inkspot:inkspot /home/inkspot/www
mkdir /home/inkspot/bin
chown inkspot:inkspot /home/inkspot/bin
cp -R bin/* /home/inkspot/bin

echo "Updating umask..."
echo 'session optional pam_umask.so umask=007' >> /etc/pam.d/common-session

echo "Installing required software..."
apt-get -q install postgresql libpam-pgsql                     # Database
apt-get -q install php5 php5-cli php5-cgi php5-pgsql           # PHP Stuff
apt-get -q install nginx spawn-fcgi mono-fastcgi-server        # WebServer Stuff
apt-get -q install postfix-tls postfix-pgsql                   # SMTP  Stuff
apt-get -q install dovecot-common dovecot-imapd dovecot-pop3d  # POP/IMAP Stuff
apt-get -q install spamassassin                                # Anti-Spam Stuff
apt-get -q install clamsmtp clamav-freshclam                   # Anti-Virus Stuff

echo "Running freshclam for the first time..."
freshclam

echo "Setting up inKSpot Database..."
echo "CREATE USER inkspot;" | sudo -u postgres psql
echo "DROP DATABASE inkspot;" | sudo -u postgres psql
echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo -u postgres psql
sudo -u inkspot psql inkspot < support/inkspot.sql


