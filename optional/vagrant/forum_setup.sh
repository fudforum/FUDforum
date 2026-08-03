#!/usr/bin/env bash
set -e

WWW_ROOT=/var/www/html
mkdir -p "$WWW_ROOT"
cd "$WWW_ROOT"

if [ -e /gitrepo/create_fudforum_archive ]; then
    echo "Build FUDforum installer..."
    cp /gitrepo/install.php .
    php /gitrepo/create_fudforum_archive /gitrepo/install 1 > fudforum_archive
    ls -ltr
else
    echo "Download FUDforum installer..."
    wget https://raw.githubusercontent.com/fudforum/FUDforum/master/install.php
fi

echo "Create FUDforum config file..."
cat > install.ini <<EOF
#WWW_ROOT         = "http://192.168.56.50/"
WWW_ROOT         = "http://localhost:8080/"
SERVER_ROOT      = "$WWW_ROOT"
SERVER_DATA_ROOT = "$WWW_ROOT/data"
DBHOST           = localhost
DBHOST_USER      = fuduser
DBHOST_PASSWORD  = fudpass
DBHOST_DBNAME    = fuddb
DBHOST_TBL_PREFIX= fud30_
DBHOST_DBTYPE    = mysqli
COOKIE_DOMAIN    = localhost
LANGUAGE         = en
TEMPLATE         = default
ROOT_LOGIN       = admin
ROOT_PASS        = admin
ADMIN_EMAIL      = "admin@example.com"
EOF

echo "Install FUDforum..."
php -d error_reporting=E_ALL \
    -d display_errors=1 \
    install.php install.ini

chown -R www-data:www-data "$WWW_ROOT"

echo
echo "======================================="
echo "FUDforum installed successfully"
echo
echo "URL: http://localhost:8080/"
echo "Admin: admin"
echo "Password: admin"
echo "======================================="
