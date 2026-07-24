# CLAUDE.md

本文件为 AI 助手（Claude Code 等）提供项目上下文，帮助快速理解与修改本项目。

## 项目概述

**IPTV Token 管理系统** —— 一个原生 PHP（无框架、无 Composer 依赖）编写的直播源订阅授权系统。核心功能：为每个用户签发 token，通过 token 控制直播源（m3u 播放列表）的访问权限、有效期、使用次数和每日 IP 数限制��

- 语言：PHP 8.1（PDO），前端为原生 HTML/CSS/JS
- 数据库：SQLite（默认）或 MySQL，通过 `DB_DRIVER` 切换，两者建表 SQL 分别维护
- 部署：Docker（Nginx + PHP-FPM，Alpine），镜像 `xc1992/php-token-iptv`
- 界面语言：中文

## 架构与核心流程

### 请求入口
- **`live.php`** —— 面向播放器的核心入口。播放器请求 `live.php?token=XXX&c=频道名`：
  1. 拦截浏览器 UA（禁止浏览器直接访问，返回 403）
  2. 校验 token：存在性 → status → 过期时间 → max_usage 使用次数 → max_ip_per_day 每日 IP 数
  3. 任一校验失败：记录日志并 302 重定向到 `EXPIRED_REDIRECT_URL`
  4. 通过 `token_playlists` 查该 token 授权的所有播放列表，用 curl 拉取各 m3u 源并合并
  5. `processM3UContent()` 二次加工：注入 EPG 头、"刷新时间/到期时间"提示频道、对含 token 参数的 URL 追加防盗链签名 `&exp=&sign=`
  6. 输出 `Content-Type: application/vnd.apple.mpegurl`
- **`api.php`** —— 对外 JSON API，`Authorization: Bearer <API_KEY>` 鉴权（单一静态密钥）。仅 POST，`?action=create_token|update_token`。用于外部系统程序化签发/续期 token（支持 `expire_days`/`expire_at`/`expire_date`/`add_days` 续期）。
- **`open/`** —— 开放平台接口（多接入方）。`open/create_token.php` 创建 token；`open/open_common.php` 为公共库（鉴权+签名+防重放+响应辅助）。鉴权基于 `api_apps` 表的 `app_id`/`app_secret`：请求带 `app_id`+`timestamp`+`nonce`+`sign`，签名为 SHA256 拼接密钥（参数按 key 字典序 + `http_build_query` + 末尾拼 `app_secret`），`timestamp` ±5 分钟窗口 + `nonce` 去重（`api_nonces` 表）防重放。接口文档见 `docs/OPEN_API.md`。与 `api.php` 完全独立。
- **`index.php`** —— 入口占位（跳转/首页）。

### 后台管理 `admin/`
基于 PHP `$_SESSION` 的简单账号密码登录（`ADMIN_USER`/`ADMIN_PASS` 明文比对，见 `login.php`）。所有页面开头校验 `$_SESSION['admin_logged_in']`。
- `dashboard.php` 控制台统计｜`tokens.php` / `token_add.php` / `token_edit.php` / `token_delete.php` token 增删改查（含搜索、到期/状态筛选）
- `playlists.php` 播放列表管理｜`logs.php` 访问日志｜`ip_query.php` IP 归属地查询（结果缓存到 `admin/cache/ip_*.json`）
- `api_apps.php` 开放平台接入方管理（app_id/secret 增删改、启停、白名单、重置密钥）
- **`db_functions.php`** —— 全部数据库操作函数库（连接、token/playlist/log 的 CRUD、分页、时间格式化、IP 定位）。**修改数据逻辑优先在此文件。**

### 关键机制
- **防盗链签名**：`SignatureValidator.php`。基于 `SIGNATURE_SECRET_KEY` 对 `token+exp` 做 SHA256。`live.php` 生成签名附加到源 URL；上游代理据此校验。注意 `generateSignature()` 里 `exp` 当前直接取 `expireSeconds`（即 token 的 `expire_at`），非"当前时间+时长"。
- **多驱动兼容**：`create_token`/`update_token` 会先 `PRAGMA table_info` / `SHOW COLUMNS` 探测 `max_ip_per_day` 字段是否存在（兼容未迁移的旧库）。新增字段时需同步 `init_db.php` 和迁移脚本两处。

## 数据模型（`init_db.php`）
- **`tokens`**：`token`(唯一), `expire_at`(时间戳,0=永不过期), `max_usage`/`usage_count`, `status`(1=有效), `max_ip_per_day`(迁移添加), `note`, `channel`, `created_at`/`updated_at`
- **`logs`**：`token`, `ip`, `channel`, `access_time`, `user_agent`
- **`playlists`**：`name`, `url`(完整 m3u 地址；旧版为 `name_en` 缩写，已通过迁移升级)
- **`token_playlists`**：`token_id` × `playlist_id` 多对多关联（唯一约束）
- **`api_apps`**：开放平台接入方。`app_id`(唯一), `app_secret`(签名密钥,明文存储), `name`, `status`(1=启用), `default_playlist_ids`(JSON 数组,播放列表白名单,空=不限制), `created_at`/`updated_at`
- **`api_nonces`**：开放平台防重放。`nonce`(唯一), `expire_at`；惰性清理过期记录

时间字段全部为 Unix 时间戳（整型）。时区固定 `Asia/Shanghai`（`config.php` 设置）。

## 配置（`config.php`）
从 `.env` 手动解析（非标准库）后 `define()` 常量。关键项：`DB_DRIVER`、`DB_DSN_MYSQL`/`DB_USER_MYSQL`/`DB_PASS_MYSQL`、`ADMIN_USER`/`ADMIN_PASS`、`REDIRECT_URL`、`EXPIRED_REDIRECT_URL`、`SIGNATURE_SECRET_KEY`、`API_KEY`。`.env` 不入库（见 `.gitignore`），改配置改 `.env`。

## 常用命令

### 本地开发（SQLite）
```bash
cp .env.example .env          # 编辑：DB_DRIVER=sqlite, ADMIN_PASS 等
php init_db.php               # 初始化数据库到 data/database.sqlite
php -S localhost:8000         # 启动开发服务器
# 后台: http://localhost:8000/admin/login.php
# 测试(用 curl，浏览器 UA 会被拒): curl -L "http://localhost:8000/live.php?token=test123&c=ch1"
```

### Docker
```bash
docker-compose up -d          # 启动 web(8080) + mysql(3306)，入口自动跑 init_db.php
docker-compose logs -f
docker build -t xc1992/php-token-iptv:latest .   # 构建镜像
```

### 数据库迁移脚本（对已有库增量升级，按需运行一次）
```bash
php migrate_status.php        # tokens 添加 status 字段
php migrate_max_ip.php        # tokens 添加 max_ip_per_day 字段
php migrate_playlists.php     # playlists: name_en → url，并建 token_playlists 表
php migrate_api_apps.php       # 建 api_apps（开放平台接入方）与 api_nonces（防重放）表
```

### 辅助工具
- `script/txt_to_m3u_smart.php` —— 将 txt 频道列表转换为 m3u 格式

## 修改约定与注意事项
- **无框架/无自动加载**：全部 `require_once` 手动引入；新页面参考现有 `admin/*.php` 头部模式（session + 登录校验 + `templates/header.php`/`footer.php`）。
- **SQL 必须用 PDO 预处理绑定**（现有代码已一致），防注入。
- **同时支持 SQLite 与 MySQL**：改表结构时 `init_db.php` 内两套 SQL 都要改；涉及新字段考虑写迁移脚本并在 `db_functions.php` 做字段探测兼容。
- **安全现状（改动时留意，勿降级）**：管理员密码明文比对、默认密钥/密码为可预测值（生产必须改 `.env`）。如做安全加固需先与用户确认。
- **不要提交** `.env`、`data/`、`admin/cache/`、`.idea/`、`*.tar` 等（见 `.gitignore`）。
- 界面与提示文案统一用中文。

## 参考文档
- `README.md` —— 部署与 Docker 使用
- `setup_local.md` —— 本地调试详细步骤
- `UPGRADE_GUIDE.md` —— 播放列表权限系统（name_en → url + token_playlists）升级说明
