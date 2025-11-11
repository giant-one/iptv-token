<?php
/**
 * txt_to_m3u_smart.php
 * 智能 IPTV 源分组生成 M3U
 *
 * 自动识别 央视 / 卫视 / 地方台 / 影视 / 新闻 / 体育 / 少儿 / 纪录 / 娱乐 / 斗鱼轮播 等分组
 * 用法：
 * php txt_to_m3u_smart.php input.txt output.m3u
 */

if ($argc < 3) {
    exit("用法: php txt_to_m3u_smart.php input.txt output.m3u\n");
}

$inputFile = $argv[1];
$outputFile = $argv[2];

if (!file_exists($inputFile)) {
    exit("❌ 错误：找不到输入文件 {$inputFile}\n");
}

$lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    exit("❌ 错误：输入文件为空或无法读取。\n");
}

// 智能分组规则表（匹配优先顺序从上往下）
function detectGroup($name, $url = '') {
    // 检测是否为YY频道（斗鱼轮播）
    if (strpos($url, 'yyphp/yy.php?id=') !== false) {
        return '斗鱼轮播';
    }
    
    $rules = [
        '央视卫视'  => ['CCTV', 'CGTN', '卫视'],
        //'卫视'     => ['卫视'],
        '新闻频道' => ['新闻', '资讯', '时政'],
        '纪录频道' => ['纪录', '纪实', '探索', '地理'],
        '影视频道' => ['电影', '大片', '影院', '影迷', '影视', '影像志', '放映厅', '剧场', '影院', '抗战'],
        '综艺娱乐' => ['综艺', '娱乐', '笑傲', '戏曲', '相声', '曲艺', '春晚', '脱口秀'],
        '体育频道' => ['体育', '足球', '篮球', '乒乓', '羽毛', '赛事'],
        '少儿频道' => ['少儿', '卡通', '动漫', '动画'],
        '音乐频道' => ['音乐', 'KTV', '歌', 'MTV'],
        '地方频道' => [
            '北京', '天津', '河北', '山西', '内蒙', '辽宁', '吉林', '黑龙江',
            '上海', '江苏', '浙江', '安徽', '福建', '江西', '山东', '河南',
            '湖北', '湖南', '广东', '广西', '海南', '重庆', '四川', '贵州',
            '云南', '西藏', '陕西', '甘肃', '青海', '宁夏', '新疆', '南方', '东方', '秦腔'
        ],
        '熊猫频道' => ['熊猫频道'],
        '港澳台'   => ['香港', '澳门', '台湾', '凤凰'],
        '农业农村' => ['农业', '乡村', '农'],
        '其他'     => ['频道'], // catch-all
    ];

    foreach ($rules as $group => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($name, $kw) !== false) {
                return $group;
            }
        }
    }
    return '其他';
}

$m3uContent = "#EXTM3U\n";

foreach ($lines as $line) {
    $parts = explode(',', $line, 2);
    if (count($parts) !== 2) continue;

    $name = trim($parts[0]);
    $url = trim($parts[1]);

    if (!filter_var($url, FILTER_VALIDATE_URL)) continue;

    $group = detectGroup($name, $url);
    $m3uContent .= "#EXTINF:-1 tvg-name=\"{$name}\" group-title=\"{$group}\",{$name}\n{$url}\n";
}

file_put_contents($outputFile, $m3uContent);
echo "✅ 已生成：{$outputFile}\n";
?>
