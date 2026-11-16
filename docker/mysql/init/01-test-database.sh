#!/bin/sh
# Runs once, when the MySQL volume is created: test database used by deploy.sh.
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS satis_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON satis_test.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
