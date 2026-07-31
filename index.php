<?php
// 根路径直接访问返回 403，避免暴露后台入口
http_response_code(403);
exit;
?>
