FROM php:8.1-fpm-alpine3.14

# 安装依赖和PHP扩展
ENV TZ=Asia/Shanghai

# 安装依赖（Alpine 用 apk）
RUN apk update && apk add --no-cache \
    sqlite-dev \
    bash \
    nginx \
    netcat-openbsd \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite \
    && docker-php-ext-enable pdo_mysql pdo_sqlite

# 配置Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . /var/www/html/

# 创建数据目录并设置权限
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 755 /var/www/html/data

# 设置默认环境变量
ENV DB_DRIVER=sqlite

# 暴露端口
EXPOSE 80

# 启动脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/bin/bash", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
