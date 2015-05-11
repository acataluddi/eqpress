#!/bin/bash
export PATH=/bin:/usr/bin:/sbin:/usr/sbin:/usr/local/bin:/usr/local/sbin
if [ -z $1 ]; then
	echo  -n "Site you want to change the password for: "
	read SITE
else
	SITE=$1
fi
DOCROOT=/var/www/${SITE}/wordpress
if [ ! -d ${DOCROOT} ]; then
	echo ${DOCROOT} does not exist on this node.
	exit 1
fi
USER_INFO=($(grep DB_USER ${DOCROOT}/wp-config.php |tr "'" '\n'))
USER=${USER_INFO[3]}
if ! grep ${USER} /etc/passwd > /dev/null; then
	echo ${USER} does not exist.
	echo Maybe ${SITE} is wrong?
	echo Maybe ${DOCROOT} is wrong?
	exit 1
fi
SFTP_PASSWORD=`< /dev/urandom tr -dc A-Za-z0-9 | head -c23`
echo "${USER}:${SFTP_PASSWORD}" | chpasswd
PWPUSH_SFTP=`curl -k -s --data "cred=${SFTP_PASSWORD}&time=60&units=days&views=7&url_only=yes" https://www.getpendeo.com/pwpush/pwpusher_public/pw.php`
echo -n "|${USER}|${PWPUSH_SFTP}|"
