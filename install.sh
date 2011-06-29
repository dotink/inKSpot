#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the installation as root"
	exit
fi

if [ -z `which dialog` ]
	then
	echo "Installing 'dialog' and 'rpl'..."
	apt-get -q install dialog rpl
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
apt-get install postgresl libpam-pgsql                     \ # Database
				php5 php5-cli php5-cgi php5-pgsql          \ # PHP Stuff
				nginx spawn-fcgi mono-fastcgi-server       \ # WebServer Stuff
				postfix-tls postfix-pgsql                  \ # SMTP  Stuff
				dovecot-common dovecot-imapd dovecot-pop3d \ # POP/IMAP Stuff
				spamassassin                               \ # Anti-Spam Stuff
				clamsmtp clamav-freshclam                    # Anti-Virus Stuff

echo "Setting up inKSpot Database..."
echo "CREATE USER inkspot;" | sudo postgres psql
echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo postgres psql
sudo -u inkspot psql inkspot < support/inkspot.sql


