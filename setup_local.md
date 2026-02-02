# 本地调试运行指南

## 1. 环境准备

首先复制配置文件：
```bash
cp .env.example .env
```

编辑 `.env` 文件，配置本地测试环境：
```env
# 数据库驱动: sqlite 或 mysql
DB_DRIVER=sqlite

# 管理员账号
ADMIN_USER=admin
ADMIN_PASS=123456

# 直播流重定向域名 (测试用)
REDIRECT_URL=http://43.142.162.253:36400

# 过期或无效令牌重定向URL
EXPIRED_REDIRECT_URL=http://43.142.162.253:36400/expired.m3u
```

## 2. 初始化数据库

```bash
php init_db.php
```

## 3. 启动开发服务器

```bash
# 在项目根目录执行
php -S localhost:8000

# 或者指定其他端口
php -S localhost:3000
```

## 4. 访问管理后台

打开浏览器访问：http://localhost:8000/admin/login.php

使用管理员账号登录：
- 用户名：admin
- 密码：123456

## 5. 创建测试Token

在管理后台创建一个测试token，例如：
- Token: test123
- 过期时间：设置为未来时间
- 最大使用次数：10

## 6. 测试重定向功能

测试URL格式：
```
http://localhost:8000/live.php?token=test123&c=channel1&t=vip
```

这将重定向到：
```
http://43.142.162.253:36400/vip/playlist.m3u
```

如果不加 t 参数：
```
http://localhost:8000/live.php?token=test123&c=channel1
```

将重定向到：
```
http://43.142.162.253:36400/playlist.m3u
```

## 7. 测试工具

推荐使用 curl 来测试（避免浏览器直接访问限制）：

```bash
# 测试重定向
curl -I "http://localhost:8000/live.php?token=test123&c=channel1&t=vip"

# 查看完整响应
curl -L "http://localhost:8000/live.php?token=test123&c=channel1&t=vip"
```

## 8. 查看日志

访问管理后台的日志页面查看访问记录：
http://localhost:8000/admin/logs.php

## 注意事项

- 使用 SQLite 数据库文件会保存在 `data/database.sqlite`
- 首次运行会自动创建 `data` 目录
- 浏览器直接访问 live.php 会被拒绝，需要使用播放器或 curl 测试
- 修改代码后不需要重启服务器，PHP内置服务器会自动加载最新代码
