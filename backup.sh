#!/usr/bin/env bash
set -e

# -------------------------------
# 主配置文件
# -------------------------------
CFG="/root/iptv_backup.conf"
if [ ! -f "$CFG" ]; then
  echo "配置文件 $CFG 不存在，请先创建。" >&2
  exit 1
fi
# shellcheck disable=SC1090
source "$CFG"

# -------------------------------
# 创建备份目录
# -------------------------------
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

DATE=$(date +%F_%H-%M-%S)

# -------------------------------
# MySQL 数据库备份
# -------------------------------
DB_FILE="${DB_NAME}_${DATE}.sql.gz"
DB_PATH="$BACKUP_DIR/$DB_FILE"

echo "[$(date +'%F %T')] 开始备份数据库 $DB_NAME ..."

USE_MYCNF=false
if [ -f "$HOME/.my.cnf" ]; then
  USE_MYCNF=true
fi

if $USE_MYCNF; then
  docker exec "$CONTAINER_NAME" sh -c "exec mysqldump --single-transaction --quick --routines --databases ${DB_NAME}" | gzip > "$DB_PATH"
else
  if [ -z "$DB_PASS" ]; then
    echo "没有检测到 ~/.my.cnf 且 DB_PASS 空，无法登录 MySQL" >&2
    exit 1
  fi
  docker exec "$CONTAINER_NAME" sh -c "exec mysqldump -u${DB_USER} --password='${DB_PASS}' --single-transaction --quick --routines --databases ${DB_NAME}" | gzip > "$DB_PATH"
fi

echo "[$(date +'%F %T')] 本地 MySQL 备份完成: $DB_PATH (size: $(du -h "$DB_PATH" | cut -f1))"

# -------------------------------
# m3u-editor PostgreSQL 数据库备份（仅导出数据库，不再打包整个 data 目录）
# -------------------------------
PG_FILE="m3ue_${DATE}.sql.gz"
PG_PATH="$BACKUP_DIR/$PG_FILE"
PG_TMP="$BACKUP_DIR/m3ue_${DATE}.sql"

echo "[$(date +'%F %T')] 开始导出 PostgreSQL 数据库到 $PG_TMP ..."
if docker exec -t "$M3U_EDITOR_CONTAINER_NAME" pg_dump -U m3ue m3ue > "$PG_TMP"; then
  gzip -f "$PG_TMP"   # 生成 $PG_PATH
  echo "[$(date +'%F %T')] PostgreSQL 备份完成: $PG_PATH (size: $(du -h "$PG_PATH" | cut -f1))"
else
  echo "[$(date +'%F %T')] PostgreSQL 备份失败!" >&2
  rm -f "$PG_TMP"
  exit 1
fi

# -------------------------------
# 清理本地过期备份（MySQL + PostgreSQL）
# -------------------------------
if [ "$KEEP_LOCAL" = "true" ] || [ "$KEEP_LOCAL" = "True" ]; then
  find "$BACKUP_DIR" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete || true
  find "$BACKUP_DIR" -type f -name "m3ue_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete || true
fi

# -------------------------------
# 上传到 GitHub 私有仓库（git push）
# -------------------------------
if [ -n "$GIT_REPO_DIR" ]; then
  echo "[$(date +'%F %T')] 开始推送备份到 GitHub 私有仓库..."

  # 保险：今天的备份文件必须存在且非空，否则跳过 git，绝不用残缺/空仓库 force push 覆盖远端
  if [ ! -s "$DB_PATH" ] || [ ! -s "$PG_PATH" ]; then
    echo "[$(date +'%F %T')] 本次备份文件缺失或为空，跳过 GitHub 推送以保护远端已有备份" >&2
    if [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
      curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
        -d "chat_id=${TELEGRAM_CHAT_ID}" \
        -d "text=备份异常：本次备份文件缺失或为空，已跳过推送 (${DATE}) 主机: $(hostname)" >/dev/null || true
    fi
    exit 1
  fi

  # MySQL 文件
  cp "$DB_PATH" "$GIT_REPO_DIR/"
  # PostgreSQL 文件
  cp "$PG_PATH" "$GIT_REPO_DIR/"

  cd "$GIT_REPO_DIR"

  # 清理仓库内超过一个月的备份文件（工作区）
  find "$GIT_REPO_DIR" -maxdepth 1 -type f \( -name "${DB_NAME}_*.sql.gz" -o -name "m3ue_*.sql.gz" \) \
    -mtime +"${RETENTION_DAYS}" -delete || true

  # 用 orphan 分支重写历史，使 git 仓库体积恒等于当前保留的文件，永不膨胀。
  # 注意：BACKUP_DIR(db_backups/) 是本仓库的子目录，只跟踪根目录的备份文件，
  # 忽略 db_backups/ 本地存储目录，避免仓库被本地留存的 30 天备份再次撑大。
  BRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || echo main)
  git checkout --orphan __backup_tmp >/dev/null 2>&1
  git rm -r --cached . >/dev/null 2>&1 || true
  git add -f "${DB_NAME}"_*.sql.gz m3ue_*.sql.gz 2>/dev/null || true
  git commit -m "Backup at ${DATE}" >/dev/null 2>&1 || true
  git branch -D "$BRANCH" >/dev/null 2>&1 || true
  git branch -m "$BRANCH"
  git gc --prune=all --quiet >/dev/null 2>&1 || true

  PUSH_ERR=$(git push -f origin "$BRANCH" 2>&1) || {
    echo "[$(date +'%F %T')] 推送 GitHub 失败: $PUSH_ERR" >&2
    if [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
      curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
        -d "chat_id=${TELEGRAM_CHAT_ID}" \
        -d "text=备份失败：推送 GitHub 失败 (${DATE}) 主机: $(hostname)" >/dev/null || true
    fi
    exit 1
  }

  echo "[$(date +'%F %T')] 备份文件已成功推送 GitHub：$DB_FILE $PG_FILE"
fi

# -------------------------------
# Telegram 成功通知
# -------------------------------
if [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
  MSG="备份成功：
数据库：${DB_NAME}
MySQL 文件：${DB_FILE}
PostgreSQL 文件：${PG_FILE}
主机：$(hostname)
时间：$(date +'%F %T')"

  # URL encode 换行
  MSG_ENCODE=$(echo "$MSG" | sed ':a;N;$!ba;s/\n/%0A/g')
  curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
    -d "chat_id=${TELEGRAM_CHAT_ID}" \
    -d "text=${MSG_ENCODE}" >/dev/null || true
fi

echo "[$(date +'%F %T')] 完成."
exit 0