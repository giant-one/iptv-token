# IPTV Token 管理系统

简单 PHP 项目，用于基于 token 验证访问直播源。

## 传统启动步骤
1. 运行 `php init_db.php` 初始化数据库
2. 在浏览器访问 `http://yourdomain/live.php?token=XXXX&c=test`

## Docker 使用方法

> **注意**: 容器使用 **Nginx** 作为 Web 服务器，配合 PHP-FPM 提供高性能的 PHP 应用运行环境。

### 配置环境变量

1. 复制环境变量示例文件创建你的配置文件：

```bash
cp .env.example .env
```

2. 编辑 `.env` 文件，设置你的实际配置参数：

```
# 直播流重定向URL（必须修改为你的实际URL）
REDIRECT_URL=http://your-actual-iptv-source-url/playlist.m3u

# 管理员密码（建议修改默认密码）
ADMIN_PASS=your_secure_password

# 其他配置...
```

### 本地开发环境

使用 Docker Compose 快速启动开发环境：

```bash
# 构建并启动容器
docker-compose up -d

# 查看日志
docker-compose logs -f
```

访问 http://localhost:8080 即可进入管理后台。

### 使用不同数据库

#### SQLite (默认)

默认使用 SQLite 数据库，数据文件保存在 `./data/database.sqlite`。系统通过卷挂载将此目录映射到容器内部，确保数据持久化和可复用：

```yaml
volumes:
  - ./data:/var/www/html/data
```

这样即使容器被删除，SQLite 数据库文件仍然保存在宿主机上，重新创建容器时可以继续使用原有数据。

#### MySQL

如需使用 MySQL 数据库，修改 `.env` 文件：

```
# 数据库驱动
DB_DRIVER=mysql

# MySQL 配置
DB_DSN_MYSQL=mysql:host=mysql;dbname=iptv;charset=utf8mb4
DB_USER_MYSQL=iptv_user
DB_PASS_MYSQL=your_secure_password

# MySQL 容器配置
MYSQL_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_secure_root_password
```

MySQL 数据同样通过卷挂载保存在宿主机上：

```yaml
volumes:
  - mysql_data:/var/lib/mysql
```

### 使用 Docker Hub 镜像

```bash
# 拉取镜像
docker pull xc1992/php-token-iptv:latest

# 运行容器（使用环境变量配置）
docker run -d -p 80:80 --name iptv-token \
  -e DB_DRIVER=sqlite \
  -e ADMIN_USER=admin \
  -e ADMIN_PASS=your_secure_password \
  -e REDIRECT_URL=http://your-actual-iptv-source-url/playlist.m3u \
  -v $(pwd)/data:/var/www/html/data \
  xc1992/php-token-iptv:latest

# 或者使用环境变量文件
docker run -d -p 8080:80 --name iptv-token \
  --env-file .env \
  -v $(pwd)/data:/var/www/html/data \
  xc1992/php-token-iptv:latest
```

### 构建并推送到 Docker Hub

```bash
# 构建镜像
docker build -t xc1992/php-token-iptv:latest .

# 登录 Docker Hub
docker login

# 推送镜像
docker push xc1992/php-token-iptv:latest
```

修改 `xc1992` 为你的 Docker Hub 用户名。
