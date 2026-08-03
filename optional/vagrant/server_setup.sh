#!/usr/bin/env bash
set -e

export DEBIAN_FRONTEND=noninteractive

apt update
apt upgrade -y

echo "Install Nginx, MariaDB and PHP..."
apt install -y \
    nginx \
    mariadb-server \
    unzip \
    wget \
    php-fpm \
    php-cli \
    php-mysql \
    php-gd \
    php-xml \
    php-mbstring \
    php-curl \
    php-zip

systemctl enable nginx
systemctl enable mariadb
systemctl start nginx
systemctl start mariadb

echo "Secure MariaDB (basic)..."
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "FLUSH PRIVILEGES;"

echo "Create forum database..."
mysql -e "CREATE DATABASE IF NOT EXISTS fuddb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'fuduser'@'localhost' IDENTIFIED BY 'fudpass';"
mysql -e "GRANT ALL PRIVILEGES ON fuddb.* TO 'fuduser'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "Configure the NGING webserver..."
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"

cat >/etc/nginx/sites-available/default <<EOF
server {
    listen 80;
    server_name _;

    root /var/www/html;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

nginx -t

systemctl restart "php${PHP_VERSION}-fpm"
systemctl restart nginx

