<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$nicknames = ['小明', '阿强', '老王', 'Luna', 'Kaze', 'Yuki', 'Mika', '三三', '阿七', '胖虎'];
$contents = [
    '今天天气不错 (＾▽＾)',
    '这个留言板挺好看的',
    '(- v -) 路过留个脚印',
    '测试一下分页功能',
    '有没有人一起玩游戏呀',
    '代码写不完了 T_T',
    '冒个泡',
    '这个动画效果不错',
    '深夜来逛逛',
    '今天吃了火锅，好爽',
];
$replies = ['谢谢支持～', '欢迎常来', '哈哈同感', ''];

$stmt = $pdo->prepare(
    'INSERT INTO messages (nickname, email, website, content, is_private, reply, replied_at, ip, user_agent, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$count = (int)($_GET['n'] ?? 60);
$count = max(1, min(500, $count));
$now = time();

for ($i = 0; $i < $count; $i++) {
    $reply = $replies[array_rand($replies)];
    $stmt->execute([
        $nicknames[$i % count($nicknames)] . $i,
        'user' . $i . '@example.com',
        null,
        $contents[$i % count($contents)] . " #$i",
        ($i % 9 === 0) ? 1 : 0,
        $reply !== '' ? $reply : null,
        $reply !== '' ? date('Y-m-d H:i:s', $now - ($count - $i) * 60) : null,
        '127.0.0.1',
        'seed',
        date('Y-m-d H:i:s', $now - ($count - $i) * 60),
    ]);
}

echo "inserted $count rows\n";
echo "total: " . $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn() . "\n";