<?php
$pageTitle = '关系管理';
$typeFilter = (int)($_GET['type_id'] ?? 0);
$charFilter = (int)($_GET['char_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;

$where = []; $params = [];
if ($typeFilter > 0) { $where[] = 'r.type_id = ?'; $params[] = $typeFilter; }
if ($charFilter > 0) { $where[] = '(r.from_id = ? OR r.to_id = ?)'; $params[] = $charFilter; $params[] = $charFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM rel_relations r $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$stmt = $pdo->prepare(
    "SELECT r.*, t.name AS type_name, t.reverse_name AS type_reverse, t.directed,
            cf.name AS from_name, ct.name AS to_name
     FROM rel_relations r
     JOIN rel_relation_types t ON t.id = r.type_id
     JOIN rel_characters cf ON cf.id = r.from_id
     JOIN rel_characters ct ON ct.id = r.to_id
     $whereSql
     ORDER BY r.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));
$rows = $stmt->fetchAll();

$allTypes = rel_all_types();
$allChars = rel_all_characters();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="page-admin">
<?php require __DIR__ . '/_topbar.php'; ?>

<main class="admin-main">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="rel-toolbar">
        <a class="btn-primary btn-sm" href="admin.php?action=relation_edit">+ 新建关系</a>
        <form method="get" action="admin.php" class="rel-search">
            <input type="hidden" name="action" value="relations">
            <select name="type_id">
                <option value="0">全部类型</option>
                <?php foreach ($allTypes as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $typeFilter === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="char_id">
                <option value="0">全部角色</option>
                <?php foreach ($allChars as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $charFilter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary btn-sm">筛选</button>
        </form>
    </div>

    <div class="rel-count">共 <?= $total ?> 条关系</div>

    <?php if (!$rows): ?>
        <div class="empty-hint">没有符合条件的关系。</div>
    <?php else: ?>
    <div class="rel-rel-list">
        <?php foreach ($rows as $r): ?>
            <div class="rel-rel-row">
                <a href="admin.php?action=character_edit&id=<?= (int)$r['from_id'] ?>"><?= e($r['from_name']) ?></a>
                <span class="rel-rel-dir"><?= $r['directed'] ? '→' : '↔' ?></span>
                <span class="rel-rel-type"><?= e($r['type_name']) ?></span>
                <span class="rel-rel-dir">→</span>
                <a href="admin.php?action=character_edit&id=<?= (int)$r['to_id'] ?>"><?= e($r['to_name']) ?></a>
                <?php if (!empty($r['note'])): ?>
                    <span class="rel-rel-note">（<?= e($r['note']) ?>）</span>
                <?php endif; ?>
                <div style="margin-left:auto; display:flex; gap:6px;">
                    <a class="btn-secondary btn-sm" href="admin.php?action=relation_edit&id=<?= (int)$r['id'] ?>">编辑</a>
                    <form method="post" action="admin.php?action=relation_delete" style="display:inline;"
                          onsubmit="return confirm('确认删除？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn-danger btn-sm">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="?action=relations&type_id=<?= $typeFilter ?>&char_id=<?= $charFilter ?>&page=<?= $page - 1 ?>">← 上一页</a>
        <?php else: ?>
            <span class="disabled">← 上一页</span>
        <?php endif; ?>
        <span class="page-info">第 <?= $page ?> / <?= $totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
            <a href="?action=relations&type_id=<?= $typeFilter ?>&char_id=<?= $charFilter ?>&page=<?= $page + 1 ?>">下一页 →</a>
        <?php else: ?>
            <span class="disabled">下一页 →</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</main>

<script src="../app.js"></script>
</body>
</html>