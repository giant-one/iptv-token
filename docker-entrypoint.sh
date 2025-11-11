#!/bin/bash
set -e

# 等待MySQL就绪（如果使用MySQL）
if [ "$DB_DRIVER" = "mysql" ]; then
  echo "Waiting for MySQL to be ready..."
  MYSQL_HOST=$(echo $DB_DSN_MYSQL | grep -oP '(?<=host=)[^;]+')
  MYSQL_PORT=$(echo $DB_DSN_MYSQL | grep -oP '(?<=port=)[^;]+' || echo 3306)
  
  while ! nc -z $MYSQL_HOST $MYSQL_PORT; do
    echo "MySQL is unavailable - sleeping"
    sleep 1
  done
  echo "MySQL is up - continuing"
fi

# 初始化数据库
echo "Initializing database..."
php /var/www/html/init_db.php

# 确保目录权限正确
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chown -R www-data:www-data /var/www/html/data

# 确保Nginx日志目录存在
mkdir -p /var/log/nginx
chown -R www-data:www-data /var/log/nginx

# 执行CMD命令（启动PHP-FPM和Nginx）
exec "$@"
