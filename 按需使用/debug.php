<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== settings 全部内容 ===\n";
foreach ($pdo->query('SELECT key, value FROM settings ORDER BY key') as $row) {
    $v = $row['value'];
    if ($row['key'] === 'admin_password_hash') {
        $v = substr($v, 0, 20) . '... (len=' . strlen($v) . ')';
    }
    echo $row['key'] . ' = ' . $v . "\n";
}

echo "\n=== 密码验证 ===\n";
$hash = setting('admin_password_hash');
echo "admin123 是否正确: " . (password_verify('admin123', $hash) ? 'YES' : 'NO') . "\n";
echo "hash 长度: " . strlen($hash) . "\n";