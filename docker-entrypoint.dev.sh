#!/bin/sh
set -e

DB_DRIVER=${DB_DRIVER:-mysql}
DB_DSN_MYSQL=${DB_DSN_MYSQL:-"mysql:host=mysql-local;port=3306;dbname=test"}
WAIT_TIMEOUT=${WAIT_TIMEOUT:-60}

if [ "$DB_DRIVER" = "mysql" ]; then
    echo "Waiting for MySQL..."
    # 提取 host
    MYSQL_HOST=$(echo "$DB_DSN_MYSQL" | sed -n 's/.*host=\([^;]*\).*/\1/p')
    # 如果 host 里面带端口
    if echo "$MYSQL_HOST" | grep -q ":"; then
        MYSQL_PORT=${MYSQL_HOST##*:}
        MYSQL_HOST=${MYSQL_HOST%%:*}
    else
        MYSQL_PORT=3306
    fi

    count=0
    while ! nc -z "$MYSQL_HOST" "$MYSQL_PORT" >/dev/null 2>&1; do
        echo "MySQL ($MYSQL_HOST:$MYSQL_PORT) unavailable..."
        sleep 1
        count=$((count+1))

        if [ "$count" -ge "$WAIT_TIMEOUT" ]; then
            echo "Timeout waiting for MySQL"
            exit 1
        fi
    done

    echo "MySQL Ready."
fi

# 创建运行目录
mkdir -p /var/www/html/data
mkdir -p /var/log/nginx

# 初始化数据库（存在才执行）
if [ -f /var/www/html/init_db.php ]; then
    php /var/www/html/init_db.php
fi

# 只处理 data 目录权限，不处理整个项目
chown -R www-data:www-data /var/www/html/data 2>/dev/null || true
chmod -R 755 /var/www/html/data 2>/dev/null || true

chown -R www-data:www-data /var/log/nginx 2>/dev/null || true

exec "$@"