<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/relations/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* ---------- 权限验证 ---------- */
if (!is_admin()) {
    $expected = rel_api_token();
    $given = $_GET['token'] ?? $_POST['token'] ?? '';
    if (!is_string($given) || $given === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// save_positions 只有管理员能调
if (($_POST['action'] ?? '') === 'save_positions' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'admin_only'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- 数据 ---------- */
$characters = $pdo->query(
    'SELECT id, name, color, avatar_url, wiki_url, note, pos_x, pos_y FROM rel_characters ORDER BY id'
)->fetchAll();
$teams = $pdo->query('SELECT id, name, color FROM rel_teams ORDER BY id')->fetchAll();
$types = $pdo->query(
    'SELECT id, name, reverse_name, category, directed FROM rel_relation_types ORDER BY id'
)->fetchAll();
$relations = $pdo->query(
    'SELECT id, from_id, to_id, type_id, note FROM rel_relations ORDER BY id'
)->fetchAll();

$ct = [];
foreach ($pdo->query('SELECT character_id, team_id FROM rel_character_teams') as $r) {
    $ct[(int)$r['character_id']][] = (int)$r['team_id'];
}

/* ---------- 保存节点位置 ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && ($_POST['action'] ?? '') === 'save_positions') {
    $raw = (string)($_POST['positions'] ?? '');
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE rel_characters SET pos_x = ?, pos_y = ? WHERE id = ?');
    $count = 0;
    $pdo->beginTransaction();
    try {
        foreach ($list as $item) {
            $id = (int)($item['id'] ?? 0);
            $x  = isset($item['x']) ? (float)$item['x'] : null;
            $y  = isset($item['y']) ? (float)$item['y'] : null;
            if ($id <= 0) continue;
            $stmt->execute([$x, $y, $id]);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'save_failed']);
        exit;
    }

    echo json_encode(['ok' => true, 'saved' => $count]);
    exit;
}

echo json_encode([
    'characters' => array_map(fn($c) => [
        'id'     => (int)$c['id'],
        'name'   => $c['name'],
        'color'  => $c['color'] ?: '',
        'avatar' => $c['avatar_url'] ?: '',
        'wiki'   => $c['wiki_url'] ?: '',
        'note'   => $c['note'] ?: '',
        'teams'  => $ct[(int)$c['id']] ?? [],
        'x'      => $c['pos_x'] !== null ? (float)$c['pos_x'] : null,
        'y'      => $c['pos_y'] !== null ? (float)$c['pos_y'] : null,
    ], $characters),

    'teams' => array_map(fn($t) => [
        'id'    => (int)$t['id'],
        'name'  => $t['name'],
        'color' => $t['color'] ?: '',
    ], $teams),

    'types' => array_map(fn($t) => [
        'id'       => (int)$t['id'],
        'name'     => $t['name'],
        'reverse'  => $t['reverse_name'],
        'category' => $t['category'],
        'directed' => (int)$t['directed'],
    ], $types),

    'relations' => array_map(fn($r) => [
        'id'   => (int)$r['id'],
        'from' => (int)$r['from_id'],
        'to'   => (int)$r['to_id'],
        'type' => (int)$r['type_id'],
        'note' => $r['note'] ?: '',
    ], $relations),
], JSON_UNESCAPED_UNICODE);