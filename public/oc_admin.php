<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/oc_functions.php';

require_admin();

$action = (string)($_GET['action'] ?? 'list');

/* ---------- 删除 ---------- */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM ocs WHERE id = ?')->execute([$id]);
        flash_set('已删除', 'success');
    }
    redirect('oc_admin.php');
}

/* ---------- 新增/编辑 ---------- */
if ($action === 'new' || $action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $oc = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM ocs WHERE id = ?');
        $stmt->execute([$id]);
        $oc = $stmt->fetch();
        if (!$oc) { flash_set('OC 不存在', 'error'); redirect('oc_admin.php'); }
    }

    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $name   = trim((string)($_POST['name'] ?? ''));
        $month  = (int)($_POST['birth_month'] ?? 0);
        $day    = (int)($_POST['birth_day'] ?? 0);
        $year   = (int)($_POST['birth_year'] ?? 0);
        $color  = trim((string)($_POST['color'] ?? ''));
        $avatar = trim((string)($_POST['avatar_url'] ?? ''));
        $wiki   = trim((string)($_POST['wiki_url'] ?? ''));

        if ($name === '' || mb_strlen($name) > 50) $errors[] = '名字必填，不超过 50 字';
        if ($month < 1 || $month > 12) $errors[] = '月份无效';
        if ($day < 1 || $day > 31) $errors[] = '日期无效';
        if (!checkdate($month, $day, 2024) && !($month === 2 && $day === 29)) $errors[] = '日期不存在';
        if ($color === '' || !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $errors[] = '颜色格式应为 #RRGGBB';
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) $errors[] = '头像 URL 无效';
        if ($wiki !== '' && !filter_var($wiki, FILTER_VALIDATE_URL)) $errors[] = 'Wiki URL 无效';

        if (!$errors) {
            if ($action === 'new') {
                $stmt = $pdo->prepare(
                    'INSERT INTO ocs (name, birth_month, birth_day, birth_year, color, avatar_url, wiki_url, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $month, $day, $year > 0 ? $year : null, $color,
                    $avatar !== '' ? $avatar : null, $wiki !== '' ? $wiki : null, date('Y-m-d H:i:s')]);
                flash_set('已新增', 'success');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE ocs SET name=?, birth_month=?, birth_day=?, birth_year=?, color=?, avatar_url=?, wiki_url=? WHERE id=?'
                );
                $stmt->execute([$name, $month, $day, $year > 0 ? $year : null, $color,
                    $avatar !== '' ? $avatar : null, $wiki !== '' ? $wiki : null, $id]);
                flash_set('已保存', 'success');
            }
            redirect('oc_admin.php');
        }
    }

    require __DIR__ . '/../includes/views/oc_admin_edit.php';
    exit;
}

/* ---------- 批量导入 ---------- */
if ($action === 'import') {
    $importErrors = [];
    $importCount = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $json = (string)($_POST['json'] ?? '');
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $importErrors[] = 'JSON 格式错误';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO ocs (name, birth_month, birth_day, birth_year, color, avatar_url, wiki_url, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $now = date('Y-m-d H:i:s');
            foreach ($data as $i => $item) {
                if (!is_array($item)) continue;
                $name  = trim((string)($item['name'] ?? ''));
                $month = (int)($item['month'] ?? 0);
                $day   = (int)($item['day'] ?? 0);
                if ($name === '' || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
                    $importErrors[] = '第 ' . ($i + 1) . ' 条数据无效';
                    continue;
                }
                $color = trim((string)($item['color'] ?? '#ff7aab'));
                if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#ff7aab';
                $stmt->execute([
                    $name, $month, $day,
                    (int)($item['year'] ?? 0) > 0 ? (int)$item['year'] : null,
                    $color,
                    !empty($item['avatar']) ? (string)$item['avatar'] : null,
                    !empty($item['wiki']) ? (string)$item['wiki'] : null,
                    $now,
                ]);
                $importCount++;
            }
            if ($importCount > 0) {
                flash_set("成功导入 $importCount 条", 'success');
                redirect('oc_admin.php');
            }
        }
    }

    require __DIR__ . '/../includes/views/oc_admin_import.php';
    exit;
}

/* ---------- 导出 ---------- */
if ($action === 'export') {
    $rows = $pdo->query(
        'SELECT name, birth_month, birth_day, birth_year, color, avatar_url, wiki_url FROM ocs ORDER BY id'
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name'  => $r['name'],
            'month' => (int)$r['birth_month'],
            'day'   => (int)$r['birth_day'],
            'year'  => $r['birth_year'] ? (int)$r['birth_year'] : null,
            'color' => $r['color'],
            'avatar'=> $r['avatar_url'],
            'wiki'  => $r['wiki_url'],
        ];
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="ocs-' . date('Ymd-His') . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ---------- 列表 ---------- */
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE name LIKE ?';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ocs $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare(
    "SELECT * FROM ocs $where ORDER BY birth_month, birth_day, name LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$rows = $stmt->fetchAll();

$flash = flash_get();

require __DIR__ . '/../includes/views/oc_admin_list.php';