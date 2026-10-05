<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$samples = [
    ['小明', 'xiaoming@example.com', '第一条测试留言 (＾▽＾)'],
    ['阿强', 'aqiang@example.com', '今天天气不错'],
    ['Luna', 'luna@example.com', '测试一下分页功能'],
    ['老王', 'laowang@example.com', '这个留言板挺好看的'],
    ['Kaze', 'kaze@example.com', '(- v -) 路过留个脚印'],
];

$stmt = $pdo->prepare(
    'INSERT INTO messages (nickname, email, website, content, is_private, ip, user_agent, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

for ($i = 1; $i <= 25; $i++) {
    $s = $samples[$i % count($samples)];
    $stmt->execute([
        $s[0] . $i,
        $s[1],
        null,
        $s[2] . " #$i",
        ($i % 7 === 0) ? 1 : 0,   // 每 7 条一个私密留言
        '127.0.0.1',
        'seed',
        date('Y-m-d H:i:s', time() - $i * 60),   // 每条往前推 1 分钟
    ]);
}

echo "inserted 25 rows\n";
echo "total: " . $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn() . "\n";