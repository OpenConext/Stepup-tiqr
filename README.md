# Install

Use composer to install php dependencies:

	sudo apt install composer
	composer install

# Run

## Using PHP

Use the built-in web server of php 5.4+ to run from the command line:

        php -S ip:port -t www

## Using Apache

For instance, on debian-based systems:

    apt update
    apt install -y ntp apache2 libapache2-mod-php php-dom php-gd
    a2enmod ssl
    a2ensite default-ssl.conf
    service apache2 reload

mod_ssl is useful only when using a valid TLS certificate.
Otherwise, use a TLS reverse proxy with a valid certificate and a http vhost. 
(I use [ssh-reverse-proxy](https://github.com/joostd/ssh-reverse-proxy).

- [example](vagrant/000-default.conf) Apache vhost config.

# Advanced options

Using a database like sqlite

    sudo apt install sqlite3
    sudo apt install php5-sqlite

Or mysql

    sudo apt install mysql-server
    sudo apt install php5-mysql
