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

clear

if [ $? ]; then
	echo "Updating..."
	apt-get -qq update
	apt-get -qq dist-upgrade
fi

echo "Installing base system software..."
apt-get -qq install rssh                                                         # Restricted Shell
apt-get -qq install postgresql postgresql-contrib libpam-pgsql libnss-pgsql2     # Database
apt-get -qq install pdns-backend-pgsql pdns-doc pdns-recursor pdns-server        # PowerDNS Stuff
apt-get -qq install php5 php5-cli php5-cgi php5-pgsql                            # PHP Stuff
apt-get -qq install nginx spawn-fcgi mono-fastcgi-server                         # WebServer Stuff
apt-get -qq install postfix postfix-pgsql                                        # SMTP  Stuff
apt-get -qq install dovecot-common dovecot-imapd dovecot-pop3d                   # POP/IMAP Stuff
apt-get -qq install spamassassin                                                 # Anti-Spam Stuff
apt-get -qq install clamsmtp clamav-freshclam                                    # Anti-Virus Stuff

echo "Running freshclam for the first time..."
freshclam

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
# Create our www directory
##
mkdir /home/inkspot/www
cp -R www/* /home/inkspot/www
chown -R inkspot:inkspot /home/inkspot/www
ln -s /home/inkspot/www/user/styles  /home/inkspot/www/writable/
ln -s /home/inkspot/www/user/scripts /home/inkspot/www/writable/
chown inkspot:inkspot /home/inkspot/www/writable/styles
chown inkspot:inkspot /home/inkspot/www/writable/scripts

##
# Make sure the console is only ever run as inkspot
##
chmod 6750 /home/inkspot/www/iw.console

##
# Create our NGINX configs directory
##
mkdir /home/inkspot/nginx
chown -R inkspot:inkspot /home/inkspot/nginx
chmod -R 770 /home/inkspot/nginx

##
# Allow www-data to write to nginx configs
##
chgrp www-data /etc/nginx/sites-available
chgrp www-data /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-enabled
chmod 770 /etc/nginx/sites-available

##
# Give inkspot a configuration home and copy defaults
##
mkdir /etc/inkspot
cp -R etc/inkspot/* /etc/inkspot
chown inkspot:inkspot /etc/inkspot

echo "Updating umask..."
echo 'session optional pam_umask.so umask=007' >> /etc/pam.d/common-session
rpl -q "umask 022" "umask 007" /etc/profile
umask 007

echo "Giving inkspot sudo-ability..."
cp etc/sudoers.d/inkspot /etc/sudoers.d
chown root:root /etc/sudoers.d/inkspot
chmod 440 /etc/sudoers.d/inkspot

echo "Removing Strictmodes from SSH..."
rpl  -q "StrictModes yes" "" /etc/ssh/sshd_config
rpl  -q "StrictModes no" "" /etc/ssh/sshd_config
echo "StrictModes no" >> /etc/ssh/sshd_config
/etc/init.d/ssh restart

echo "Setting up RSSH..."
cp etc/rssh.conf /etc/rssh.conf

echo "Reconfiguring postgres authentication..."
for ver_dir in `ls -1 /etc/postgresql`; do
	cp etc/postgres/pg_hba.conf /etc/postgresql/$ver_dir/main/
	chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_hba.conf
	cp etc/postgres/pg_ident.conf /etc/postgresql/$ver_dir/main/
	chown postgres:postgres /etc/postgresql/$ver_dir/main/pg_ident.conf
done
/etc/init.d/postgresql restart



echo "Setting up inKSpot database and permissions..."
ro_password=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 16 | xargs`
echo "CREATE USER inkspot"                                    | sudo -u postgres psql
echo "CREATE USER inkspot_ro PASSWORD '$ro_password';"        | sudo -u postgres psql
echo "CREATE DATABASE inkspot OWNER inkspot ENCODING 'UTF8';" | sudo -u postgres psql
psql -U inkspot < support/schema/inkspot.sql
psql -U inkspot < support/schema/auth.sql

echo "Setting up PowerDNS database user and permissions"
dns_password=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c 16 | xargs`
echo "CREATE USER inkspot_dns PASSWORD '$dns_password';"   | sudo -u postgres psql

echo "Granting permissions to inkspot_dns user"
echo "GRANT ALL ON domains TO inkspot_dns;"                | psql -U inkspot
echo "GRANT ALL ON domain_records TO inkspot_dns;"         | psql -U inkspot
echo "GRANT SELECT ON domain_supermasters TO inkspot_dns;" | psql -U inkspot
echo "GRANT ALL ON domains_id_seq TO inkspot_dns;"         | psql -U inkspot
echo "GRANT ALL ON domain_records_id_seq TO inkspot_dns;"  | psql -U inkspot

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
rpl -q \$\{password\} $ro_password /etc/nss-pgsql.conf >/dev/null

echo "Setting up Spawn-FCGI environment..."
mkdir /home/inkspot/var
mkdir /home/inkspot/var/cgi
mkdir /home/inkspot/var/cgi/domains
mkdir /home/inkspot/var/cgi/users
chown -R inkspot:inkspot /home/inkspot/var

echo "Setting up PowerDNS..."
cp    etc/powerdns/pdns.conf /etc/powerdns
cp    etc/powerdns/recursor.conf /etc/powerdns
cp    etc/powerdns/pdns.d/inkspot.conf /etc/powerdns/pdns.d
chown pdns:inkspot /etc/powerdns/pdns.d/inkspot.conf
chmod 660 /etc/powerdns/pdns.d/inkspot.conf
chmod 664 /etc/resolv.conf
cp    etc/network/if-up.d/powerdns /etc/network/if-up.d
rpl -q \$\{password\} $dns_password /etc/powerdns/pdns.d/inkspot.conf
invoke-rc.d pdns-recursor restart
invoke-rc.d pdns restart
invoke-rc.d networking restart

echo "Setting up NGINX..."
cp    etc/nginx/sites-available/inkspot /etc/nginx/sites-available/inkspot
ln -s /etc/nginx/sites-available/inkspot /etc/nginx/sites-enabled/inkspot
invoke-rc.d nginx restart

echo "Running setup..."
sleep 10
clear
www/iw.console `dirname $(readlink -f $0)`/support/setup.php
