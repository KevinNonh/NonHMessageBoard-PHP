<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/../config.php';

$storageDir = dirname($config['db_path']);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

$pdo = new PDO('sqlite:' . $config['db_path'], null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA busy_timeout = 5000');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nickname TEXT NOT NULL,
    email TEXT NOT NULL,
    website TEXT,
    content TEXT NOT NULL,
    is_private INTEGER NOT NULL DEFAULT 0,
    reply TEXT,
    replied_at TEXT,
    ip TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL
)
SQL);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_created ON messages (created_at DESC)');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS ocs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    birth_month INTEGER NOT NULL,
    birth_day INTEGER NOT NULL,
    birth_year INTEGER,
    color TEXT NOT NULL DEFAULT '#ff7aab',
    avatar_url TEXT,
    wiki_url TEXT,
    created_at TEXT NOT NULL
)
SQL);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_ocs_birth ON ocs (birth_month, birth_day)');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
)
SQL);

// 加载 settings
$settings = [];
foreach ($pdo->query('SELECT key, value FROM settings') as $row) {
    $settings[$row['key']] = $row['value'];
}

// 首次初始化
$initial = [
    'site_name' => $config['site_name'] ?? '留言板',
    'site_icon' => $config['site_icon'] ?? '💬',
    'timezone'  => $config['timezone']  ?? 'Asia/Shanghai',
    'admin_username' => $config['admin_username'] ?? 'admin',
	'announcement'   => '',
	'footer_html'    => '',
];
foreach ($initial as $k => $v) {
    if (!isset($settings[$k])) {
        $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
        $stmt->execute([$k, $v]);
        $settings[$k] = (string)$v;
    }
}

if (!isset($settings['admin_password_hash'])) {
    $hash = !empty($config['admin_password_hash'])
        ? (string)$config['admin_password_hash']
        : password_hash((string)($config['admin_password'] ?? 'admin123'), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
    $stmt->execute(['admin_password_hash', $hash]);
    $settings['admin_password_hash'] = $hash;
}

// 应用时区
date_default_timezone_set($settings['timezone'] ?: 'Asia/Shanghai');

require_once __DIR__ . '/functions.php';