#!/bin/bash
set -e

# 等待MySQL就绪（如果使用MySQL）
DB_DRIVER=${DB_DRIVER:-mysql}
DB_DSN_MYSQL=${DB_DSN_MYSQL:-"mysql:host=mysql-test;port=3306;dbname=test"}
WAIT_TIMEOUT=${WAIT_TIMEOUT:-60}   # 等待超时秒数

if [ "$DB_DRIVER" = "mysql" ]; then
  echo "Waiting for MySQL to be ready..."

  # 提取 host 和 port，兼容 BusyBox sed
  MYSQL_HOST=$(echo "$DB_DSN_MYSQL" | sed -n 's/.*host=\([^;]*\).*/\1/p')
  MYSQL_PORT=$(echo "$DB_DSN_MYSQL" | sed -n 's/.*port=\([^;]*\).*/\1/p')
  MYSQL_PORT=${MYSQL_PORT:-3306}  # 默认 3306

  # 等待 MySQL 就绪，带超时
  count=0
  while ! nc -z "$MYSQL_HOST" "$MYSQL_PORT" >/dev/null 2>&1; do
    echo "MySQL ($MYSQL_HOST:$MYSQL_PORT) is unavailable - sleeping 1s"
    sleep 1
    count=$((count + 1))
    if [ "$count" -ge "$WAIT_TIMEOUT" ]; then
      echo "Timeout ($WAIT_TIMEOUT seconds) waiting for MySQL at $MYSQL_HOST:$MYSQL_PORT"
      exit 1
    fi
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
