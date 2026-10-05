<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS rel_characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    color TEXT DEFAULT '',
    avatar_url TEXT,
    wiki_url TEXT,
    note TEXT,
    sort INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
)
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS rel_teams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    color TEXT DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0
)
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS rel_character_teams (
    character_id INTEGER NOT NULL,
    team_id INTEGER NOT NULL,
    PRIMARY KEY (character_id, team_id)
)
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS rel_relation_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    reverse_name TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT '其他',
    directed INTEGER NOT NULL DEFAULT 0,
    sort INTEGER NOT NULL DEFAULT 0
)
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS rel_relations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_id INTEGER NOT NULL,
    to_id INTEGER NOT NULL,
    type_id INTEGER NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL
)
SQL);

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_rel_rel_from ON rel_relations(from_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_rel_rel_to   ON rel_relations(to_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_rel_rel_type ON rel_relations(type_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_rel_ct_char  ON rel_character_teams(character_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_rel_ct_team  ON rel_character_teams(team_id)');

// 兼容旧库：给 rel_characters 加 pos_x / pos_y
$cols = [];
foreach ($pdo->query('PRAGMA table_info(rel_characters)') as $row) {
    $cols[$row['name']] = true;
}
if (!isset($cols['pos_x'])) {
    $pdo->exec('ALTER TABLE rel_characters ADD COLUMN pos_x REAL');
}
if (!isset($cols['pos_y'])) {
    $pdo->exec('ALTER TABLE rel_characters ADD COLUMN pos_y REAL');
}

// 首次运行：导入预设关系类型
$count = (int)$pdo->query('SELECT COUNT(*) FROM rel_relation_types')->fetchColumn();
if ($count === 0) {
    $preset = require __DIR__ . '/preset_types.php';
    $stmt = $pdo->prepare(
        'INSERT INTO rel_relation_types (name, reverse_name, category, directed, sort)
         VALUES (?, ?, ?, ?, ?)'
    );
    $sort = 0;
    foreach ($preset as $row) {
        $stmt->execute([$row[0], $row[1], $row[2], $row[3] ? 1 : 0, $sort++]);
    }
}

require_once __DIR__ . '/functions.php';

/**
 * 关系图专用：未登录时跳到留言板后台登录页（绝对路径）
 */
function require_rel_admin(): void
{
    if (is_admin()) return;

    // 记录想去的 URL，登录后跳回来
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri !== '' && preg_match('~^/[^\s]*$~', $uri)) {
        $_SESSION['admin_redirect'] = $uri;
    }

    // 从 /public/relations/admin.php 推出 /public/admin.php
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $dir    = rtrim(dirname($script), '/\\');        // /public/relations
    $parent = rtrim(dirname($dir), '/\\');           // /public
    if ($parent === '' || $parent === '/' || $parent === '\\' || $parent === '.') {
        $parent = '';
    }
    redirect($parent . '/admin.php?action=login');
}