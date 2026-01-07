<?php
// 数据库连接和操作函数库

// 获取数据库连接
function get_db_connection() {
    static $db = null;
    
    if ($db === null) {
        try {
            if (DB_DRIVER === 'sqlite') {
                $db = new PDO('sqlite:' . DB_FILE);
            } else {
                $db = new PDO(DB_DSN_MYSQL, DB_USER_MYSQL, DB_PASS_MYSQL);
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    return $db;
}

// 获取所有Token
function get_all_tokens($limit = null, $offset = null, $search = null, $expire_filter = null) {
    $db = get_db_connection();
    
    $sql = 'SELECT * FROM tokens';
    $where_conditions = [];
    $params = [];
    
    // 搜索条件
    if (!empty($search)) {
        $where_conditions[] = 'token LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    
    // 到期时间筛选
    if (!empty($expire_filter)) {
        $now = time();
        switch ($expire_filter) {
            case 'expired':
                $where_conditions[] = 'expire_at > 0 AND expire_at < :now';
                $params[':now'] = $now;
                break;
            case '3':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (3 * 24 * 3600);
                break;
            case '7':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (7 * 24 * 3600);
                break;
            case '15':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (15 * 24 * 3600);
                break;
            case '30':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (30 * 24 * 3600);
                break;
            case '365':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (365 * 24 * 3600);
                break;
        }
    }
    
    if (!empty($where_conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $sql .= ' ORDER BY created_at DESC';
    
    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :offset, :limit';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        if ($key == ':limit' || $key == ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取Token总数
function get_tokens_count($search = null, $expire_filter = null) {
    $db = get_db_connection();
    
    $sql = 'SELECT COUNT(*) FROM tokens';
    $where_conditions = [];
    $params = [];
    
    // 搜索条件
    if (!empty($search)) {
        $where_conditions[] = 'token LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    
    // 到期时间筛选
    if (!empty($expire_filter)) {
        $now = time();
        switch ($expire_filter) {
            case 'expired':
                $where_conditions[] = 'expire_at > 0 AND expire_at < :now';
                $params[':now'] = $now;
                break;
            case '3':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (3 * 24 * 3600);
                break;
            case '7':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (7 * 24 * 3600);
                break;
            case '15':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (15 * 24 * 3600);
                break;
            case '30':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (30 * 24 * 3600);
                break;
            case '365':
                $where_conditions[] = 'expire_at > :now AND expire_at <= :expire_time';
                $params[':now'] = $now;
                $params[':expire_time'] = $now + (365 * 24 * 3600);
                break;
        }
    }
    
    if (!empty($where_conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchColumn();
}

// 根据ID获取Token
function get_token_by_id($id) {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM tokens WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 检查Token是否存在
function token_exists($token) {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT COUNT(*) FROM tokens WHERE token = :token');
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

// 生成唯一Token
function generate_unique_token($length = 32) {
    do {
        $token = bin2hex(random_bytes($length / 2));
    } while (token_exists($token));
    
    return $token;
}

// 创建新Token
function create_token($data) {
    $db = get_db_connection();
    
    $sql = 'INSERT INTO tokens (token, expire_at, max_usage, usage_count, note, channel, created_at, updated_at) 
            VALUES (:token, :expire_at, :max_usage, :usage_count, :note, :channel, :created_at, :updated_at)';
            
    $stmt = $db->prepare($sql);
    $now = time();
    
    $stmt->bindValue(':token', $data['token']);
    $stmt->bindValue(':expire_at', $data['expire_at'] ?: 0, PDO::PARAM_INT);
    $stmt->bindValue(':max_usage', $data['max_usage'] ?: 0, PDO::PARAM_INT);
    $stmt->bindValue(':usage_count', 0, PDO::PARAM_INT);
    $stmt->bindValue(':note', $data['note']);
    $stmt->bindValue(':channel', $data['channel'] ?? null);
    $stmt->bindValue(':created_at', $now, PDO::PARAM_INT);
    $stmt->bindValue(':updated_at', $now, PDO::PARAM_INT);
    
    return $stmt->execute();
}

// 更新Token
function update_token($id, $data) {
    $db = get_db_connection();
    
    $sql = 'UPDATE tokens SET 
            token = :token, 
            expire_at = :expire_at, 
            max_usage = :max_usage, 
            note = :note, 
            channel = :channel, 
            updated_at = :updated_at 
            WHERE id = :id';
            
    $stmt = $db->prepare($sql);
    
    $stmt->bindValue(':token', $data['token']);
    $stmt->bindValue(':expire_at', $data['expire_at'] ?: 0, PDO::PARAM_INT);
    $stmt->bindValue(':max_usage', $data['max_usage'] ?: 0, PDO::PARAM_INT);
    $stmt->bindValue(':note', $data['note']);
    $stmt->bindValue(':channel', $data['channel'] ?? null);
    $stmt->bindValue(':updated_at', time(), PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

// 删除Token
function delete_token($id) {
    $db = get_db_connection();
    $stmt = $db->prepare('DELETE FROM tokens WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

// 获取访问日志
function get_logs($limit = null, $offset = null, $token = null) {
    $db = get_db_connection();
    
    $sql = 'SELECT * FROM logs';
    $params = [];
    
    if ($token) {
        $sql .= ' WHERE token = :token';
        $params[':token'] = $token;
    }
    
    $sql .= ' ORDER BY access_time DESC';
    
    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :offset, :limit';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        if ($key == ':limit' || $key == ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取日志总数
function get_logs_count($token = null) {
    $db = get_db_connection();
    
    $sql = 'SELECT COUNT(*) FROM logs';
    $params = [];
    
    if ($token) {
        $sql .= ' WHERE token = :token';
        $params[':token'] = $token;
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchColumn();
}

// 格式化时间戳
function format_timestamp($timestamp, $format = 'Y-m-d H:i:s') {
    if (!$timestamp) return '永不过期';
    return date($format, $timestamp);
}

// 计算剩余天数
function calculate_remaining_days($expire_at) {
    if (!$expire_at || $expire_at == 0) {
        return '永不过期';
    }
    
    $now = time();
    $remaining_seconds = $expire_at - $now;
    
    if ($remaining_seconds <= 0) {
        return '<span style="color: #e74c3c;">已过期</span>';
    }
    
    $remaining_days = ceil($remaining_seconds / (24 * 3600));
    
    // 根据剩余天数设置不同颜色
    if ($remaining_days <= 3) {
        return '<span style="color: #e74c3c; font-weight: bold;">' . $remaining_days . ' 天</span>';
    } elseif ($remaining_days <= 7) {
        return '<span style="color: #f39c12; font-weight: bold;">' . $remaining_days . ' 天</span>';
    } elseif ($remaining_days <= 30) {
        return '<span style="color: #f1c40f;">' . $remaining_days . ' 天</span>';
    } else {
        return '<span style="color: #27ae60;">' . $remaining_days . ' 天</span>';
    }
}

// 生成分页HTML
function generate_pagination($total_items, $items_per_page, $current_page, $url_pattern) {
    if ($total_items <= $items_per_page) return '';
    
    $total_pages = ceil($total_items / $items_per_page);
    $pagination = '<ul class="pagination">';
    
    // 上一页
    if ($current_page > 1) {
        $pagination .= '<li><a href="' . sprintf($url_pattern, $current_page - 1) . '">&laquo; 上一页</a></li>';
    }
    
    // 页码
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = $i == $current_page ? 'class="active"' : '';
        $pagination .= '<li><a ' . $active . ' href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
    }
    
    // 下一页
    if ($current_page < $total_pages) {
        $pagination .= '<li><a href="' . sprintf($url_pattern, $current_page + 1) . '">下一页 &raquo;</a></li>';
    }
    
    $pagination .= '</ul>';
    return $pagination;
}

// 播放列表相关函数

// 获取所有播放列表
function get_all_playlists($limit = null, $offset = null) {
    $db = get_db_connection();
    
    $sql = 'SELECT * FROM playlists ORDER BY created_at DESC';
    $params = [];
    
    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :offset, :limit';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取播放列表总数
function get_playlists_count() {
    $db = get_db_connection();
    $stmt = $db->query('SELECT COUNT(*) FROM playlists');
    return $stmt->fetchColumn();
}

// 根据ID获取播放列表
function get_playlist_by_id($id) {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT * FROM playlists WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 创建新播放列表
function create_playlist($data) {
    $db = get_db_connection();
    
    $sql = 'INSERT INTO playlists (name, name_en, created_at, updated_at) 
            VALUES (:name, :name_en, :created_at, :updated_at)';
            
    $stmt = $db->prepare($sql);
    $now = time();
    
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':name_en', $data['name_en']);
    $stmt->bindValue(':created_at', $now, PDO::PARAM_INT);
    $stmt->bindValue(':updated_at', $now, PDO::PARAM_INT);
    
    return $stmt->execute();
}

// 更新播放列表
function update_playlist($id, $data) {
    $db = get_db_connection();
    
    $sql = 'UPDATE playlists SET 
            name = :name, 
            name_en = :name_en, 
            updated_at = :updated_at 
            WHERE id = :id';
            
    $stmt = $db->prepare($sql);
    
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':name_en', $data['name_en']);
    $stmt->bindValue(':updated_at', time(), PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

// 删除播放列表
function delete_playlist($id) {
    $db = get_db_connection();
    $stmt = $db->prepare('DELETE FROM playlists WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}
