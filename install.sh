#!/bin/bash

if [ `id -u` != '0' ]
	then
	echo "You must run the installation as root"
	exit
fi

port=8765

REQUIRED_BASE_SOFTWARE=(dialog rpl sudo rlwrap curl)

for i in ${REQUIRED_BASE_SOFTWARE[*]}; do
	if [ -z `which $i` ]; then
		apt-get -qq install $i;
	fi
done

dialog --yesno "Do you wish to update the system? (RECOMMENDED)" 5 80; res=$? ; clear

if [ $res == 0 ]; then
	echo "Updating..."
	apt-get -qq update
	apt-get -qq dist-upgrade
fi

##
# Create accounts, these will output their own information
##
addgroup --system inkspot
adduser  --system --ingroup inkspot inkspot
chsh inkspot -s /bin/bash

##
# Add inkspot user to root group and allow write to /home
##
adduser inkspot root
adduser inkspot www-data
adduser www-data inkspot
mkdir /home/users
chgrp inkspot /home/users
mkdir /home/domains
chgrp inkspot /home/domains
chmod -R 771 /home

##
# Create our bin directory
##
mkdir /home/inkspot/bin
cp -R bin/* /home/inkspot/bin
chown -R inkspot:inkspot /home/inkspot/bin

##
# Create our NGINX configs directory
##
mkdir /home/inkspot/nginx
chown -R inkspot:inkspot /home/inkspot/nginx
chmod -R 770 /home/inkspot/nginx

##
# Give inkspot a configuration home and copy defaults
##
mkdir /etc/inkspot
cp -R etc/inkspot/* /etc/inkspot
chown inkspot:inkspot /etc/inkspot

##
# Update our umask
##
echo 'session optional pam_umask.so umask=007' >> /etc/pam.d/common-session
rpl -q "umask 022" "umask 007" /etc/profile
umask 007

##
# Give inkspot sudo-ability
##
cp etc/sudoers.d/inkspot /etc/sudoers.d
chown root:root /etc/sudoers.d/inkspot
chmod 440 /etc/sudoers.d/inkspot

echo "Installing base system software..."
apt-get -qq install rssh                              # Restricted Shell
apt-get -qq install libpam-pgsql libnss-pgsql2        # Database Auth
apt-get -qq install php5 php5-cli php5-fpm php5-pgsql # PHP Stuff
apt-get -qq install nginx                             # WebServer Stack

echo "Setting up PAM for PostgreSQL..."
cp    etc/pam_pgsql.conf /etc/
chmod 644 /etc/pam_pgsql.conf
cp    support/pam-configs/pgsql /usr/share/pam-configs/
pam-auth-update --package

echo "Setting up NSS for PostgreSQL..."
cp    etc/nss-pgsql.conf /etc/
chmod 644 /etc/nss-pgsql.conf
cp    etc/nss-pgsql-root.conf /etc/
chmod 644 /etc/nss-pgsql-root.conf
cp    etc/nsswitch.conf /etc/

##
# Give RSSH Our Defaults
##
cp etc/rssh.conf /etc/rssh.conf

##
# Remove strict modes from SSH
##
rpl  -q "StrictModes yes" "" /etc/ssh/sshd_config
rpl  -q "StrictModes no" "" /etc/ssh/sshd_config
echo "StrictModes no" >> /etc/ssh/sshd_config

##
# Restart Services
##
service ssh restart

##
# Allow www-data to write to nginx configs
##
chgrp www-data /etc/nginx/sites-available
chgrp www-data /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-available

echo "Setting up PHP-FPM environment..."
mkdir /home/inkspot/var
mkdir /home/inkspot/var/cgi
mkdir /home/inkspot/var/cgi/domains
mkdir /home/inkspot/var/cgi/users
chown -R inkspot:inkspot /home/inkspot/var

echo "Setting up NGINX..."
cp    etc/nginx/sites-available/inkspot /etc/nginx/sites-available/inkspot
ln -s /etc/nginx/sites-available/inkspot /etc/nginx/sites-enabled/inkspot
service nginx reload


dialog --yesno "Is this system going to be a master server?" 5 80; res=$?; clear

if [ $res == 0 ]; then
	echo "Installing master services..."
	apt-get -qq install postgresql postgresql-contrib                         # Database
	apt-get -qq install pdns-server pdns-recursor pdns-backend-pgsql pdns-doc # DNS Server

	##
	# PostgreSQL Setup
	##
	echo "Reconfiguring postgres authentication..."
	for ver_dir in `ls -1 /etc/postgresql`; do
		cp etc/postgres/pg_hba.conf /etc/postgresql/$ver_dir/main/
		chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_hba.conf
		cp etc/postgres/pg_ident.conf /etc/postgresql/$ver_dir/main/
		chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_ident.conf
	done

	##
	# Restart Service
	##
	service postgresql restart

	echo "Setting up inKSpot database and permissions..."
	ro_password=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 16 | xargs`
	echo "CREATE USER inkspot"                                    | sudo -u postgres psql
	echo "CREATE USER inkspot_ro PASSWORD '$ro_password';"        | sudo -u postgres psql
	echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo -u postgres psql
	psql -U inkspot < support/schema/inkspot.sql
	psql -U inkspot < support/schema/auth.sql
	echo "GRANT SELECT ON users TO inkspot_ro;"                   | psql -U inkspot
	echo "GRANT SELECT ON groups TO inkspot_ro;"                  | psql -U inkspot
	echo "GRANT SELECT ON user_groups TO inkspot_ro;"             | psql -U inkspot

	##
	# Replace password for NSS
	##
	rpl -q \$\{password\} $ro_password /etc/nss-pgsql.conf >/dev/null

	##
	# Power DNS Setup
	##
	echo "Setting up PowerDNS database user and permissions"
	dns_password=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 16 | xargs`
	echo "CREATE USER inkspot_dns PASSWORD '$dns_password';"   | sudo -u postgres psql

	echo "Granting permissions to inkspot_dns user"
	echo "GRANT ALL ON domains TO inkspot_dns;"                | psql -U inkspot
	echo "GRANT ALL ON domain_records TO inkspot_dns;"         | psql -U inkspot
	echo "GRANT SELECT ON domain_supermasters TO inkspot_dns;" | psql -U inkspot
	echo "GRANT ALL ON domains_id_seq TO inkspot_dns;"         | psql -U inkspot
	echo "GRANT ALL ON domain_records_id_seq TO inkspot_dns;"  | psql -U inkspot

	echo "Setting up PowerDNS..."
	cp    etc/powerdns/pdns.conf /etc/powerdns
	cp    etc/powerdns/recursor.conf /etc/powerdns
	cp    etc/powerdns/pdns.d/inkspot.conf /etc/powerdns/pdns.d
	chown pdns:inkspot /etc/powerdns/pdns.d/inkspot.conf
	chmod 660 /etc/powerdns/pdns.d/inkspot.conf
	chmod 664 /etc/resolv.conf
	cp    etc/network/if-up.d/powerdns /etc/network/if-up.d

	##
	# Replace password for DNS
	##
	rpl -q \$\{password\} $dns_password /etc/powerdns/pdns.d/inkspot.conf

	##
	# Restart Services
	##
	service pdns-recursor restart
	service pdns restart
	service networking restart

	dialog --yesno "Is this system going to be a mail server?" 5 80; res=$?; clear

	if [ $res == 0 ]; then
		echo "Installing mail server requirements..."
		apt-get -qq install postfix postfix-pgsql                      # Incoming Server
		apt-get -qq install dovecot-common dovecot-imapd dovecot-pop3d # Outgoing Servers
		apt-get -qq install spamassassin                               # Spam Assassin
		apt-get -qq install clamsmtp clamav-freshclam                  # Anti-Virus

		echo "Running freshclam for the first time..."
		freshclam
	fi
else
	echo "Installing slave services..."
	apt-get -qq install xinetd slidentd # Inet and Ident Services

	echo "Setting up identd..."
	cp etc/xinetd.d/auth /etc/xinetd.d/
	chmod 660 /etc/xinetd.d/auth

	##
	# Restart Services
	##
	service xinetd restart

	while [ 1 ]; do
		dialog --inputbox "Enter the hostname or IP address of your master server:" 5 80 2> /etc/inkspot/master_host
		if [ $? == 0 ]; then
			master_host=`cat /etc/inkspot/master_host`
			public_key=`cat /etc/ssh/ssh_host_rsa_key.pub`
			curl -X PUT --data-urlencode "public_key=$public_key" http://$master_host:$port/slaves/
			if [ $? = 0 ]; then
				clear
				echo "#############################################################################"
				echo "##  INSTALLATION COMPLETED                                                 ##"
				echo "#############################################################################"
				echo
				echo "Please log into your master server via HTTP on port $port and approve the    "
				echo "slave request."
				break
			fi
		else
			clear
			echo "#############################################################################"
			echo "##  INSTALLATION NOT COMPLETED                                             ##"
			echo "#############################################################################"
			echo
			echo "The installation could not complete as we were unable to contact the master  "
			echo "server, please re-run the installation once you have confirmed the address   "
			echo "and that the server is available."
			break
		fi
	done

fi



