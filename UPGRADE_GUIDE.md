# 播放列表权限系统升级指南

## 升级说明

本次升级将播放列表管理系统从基于"英文缩写"的方式升级为基于"完整URL+权限控制"的方式。

## 主要改进

### 1. 播放列表管理优化
- **原来**: 播放列表使用"英文缩写"字段（name_en），拼接在URL的t参数上
- **现在**: 播放列表使用完整的播放地址URL，直接存储实际的播放源地址

### 2. Token权限控制
- **原来**: Token可以访问所有播放列表
- **现在**: 创建Token时可以指定该Token拥有哪些播放列表的访问权限

### 3. 动态权限管理
- 播放地址保持不变（使用播放列表ID）
- 可以在后台动态调整用户的播放权限
- 可以随时更改播放列表的实际URL而不影响用户端

## 升级步骤

### 1. 运行数据库迁移脚本

```bash
php migrate_playlists.php
```

这个脚本会：
- 将 `playlists` 表的 `name_en` 字段改为 `url` 字段
- 创建 `token_playlists` 关联表
- 保留现有数据（将原有的 name_en 值迁移到 url 字段）

### 2. 更新播放列表

升级后，需要在后台"播放列表管理"页面将每个播放列表的URL更新为完整的播放地址：

1. 访问 `admin/playlists.php`
2. 编辑每个播放列表
3. 将"播放地址"字段更新为完整的URL（例如：`https://example.com/path/playlist.m3u`）

### 3. 为现有Token添加播放列表权限

升级后，现有的Token不会自动拥有任何播放列表权限，需要：

1. 访问 `admin/tokens.php`
2. 编辑每个Token
3. 在"播放列表权限"部分勾选该Token可以访问的播放列表

## 新的访问链接格式

### 旧格式
```
http://www.example.com/live.php?token=xxx&t=vip&c=xy
```
- `t=vip`: vip是播放列表的英文缩写

### 新格式
```
http://www.example.com/live.php?token=xxx&p=1&c=xy
```
- `p=1`: 1是播放列表的ID
- 系统会验证该Token是否有权限访问ID为1的播放列表

## 功能特性

### 1. 播放列表管理 (admin/playlists.php)
- 添加播放列表时填写完整的播放地址URL
- 表格适配长URL显示，防止横向拉伸
- 支持点击URL在新窗口打开

### 2. Token管理 (admin/token_add.php, admin/token_edit.php)
- 创建/编辑Token时可以选择播放列表权限
- 支持全选/取消全选
- 必须至少选择一个播放列表

### 3. 访问控制 (live.php)
- 验证Token是否有该播放列表的访问权限
- 无权限时返回403错误
- 直接使用数据库中配置的完整URL获取播放内容

### 4. 复制链接 (admin/tokens.php)
- 点击"复制链接"只显示该Token有权限的播放列表
- 生成的链接使用新格式（p=播放列表ID）

## 数据库变更

### playlists表
- 删除字段: `name_en`
- 新增字段: `url` (TEXT, 存储完整的播放地址URL)

### 新增表: token_playlists
```sql
CREATE TABLE token_playlists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id INTEGER NOT NULL,
    playlist_id INTEGER NOT NULL,
    created_at INTEGER,
    UNIQUE(token_id, playlist_id)
);
```

## 注意事项

1. **旧的播放链接将失效**: 升级后，使用旧格式（带t参数）的链接将无法访问
2. **需要重新分配权限**: 所有现有Token需要重新配置播放列表权限
3. **URL格式验证**: 播放地址字段要求输入有效的URL格式
4. **权限必选**: 创建Token时必须至少选择一个播放列表权限

## 回滚方案

如果需要回滚到旧版本，请：

1. 备份 `token_playlists` 表数据
2. 将 `playlists.url` 字段数据备份
3. 恢复旧版本代码
4. 手动将 `url` 字段改回 `name_en` 字段

建议在升级前做好数据库备份！
