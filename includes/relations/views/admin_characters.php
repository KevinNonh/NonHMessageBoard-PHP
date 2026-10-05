<?php
$pageTitle = '角色管理';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 36;

$where = ''; $params = [];
if ($q !== '') { $where = 'WHERE name LIKE ?'; $params[] = '%' . $q . '%'; }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM rel_characters $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$stmt = $pdo->prepare("SELECT * FROM rel_characters $where ORDER BY sort, name LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));
$rows = $stmt->fetchAll();

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
        <a class="btn-primary btn-sm" href="admin.php?action=character_edit">+ 新建角色</a>
        <form method="get" action="admin.php" class="rel-search">
            <input type="hidden" name="action" value="characters">
            <input type="text" name="q" placeholder="搜索角色名" value="<?= e($q) ?>">
            <button type="submit" class="btn-secondary btn-sm">搜索</button>
        </form>
    </div>

    <div class="rel-count">共 <?= $total ?> 个角色</div>

    <?php if (!$rows): ?>
        <div class="empty-hint">还没有角色。</div>
    <?php else: ?>
    <div class="rel-char-grid">
        <?php foreach ($rows as $c): ?>
            <div class="rel-char-card">
                <div class="rel-char-avatar"
                     style="--c: <?= e($c['color'] ?: rel_default_color((int)$c['id'])) ?>;"
                     <?= empty($c['avatar_url']) ? 'data-initial="' . e(rel_initial($c['name'])) . '"' : '' ?>>
                    <?php if (!empty($c['avatar_url'])): ?>
                        <img src="<?= e($c['avatar_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <?php endif; ?>
                </div>
                <div class="rel-char-info">
                    <div class="rel-char-name"><?= e($c['name']) ?></div>
                    <?php $teams = rel_teams_of((int)$c['id']); ?>
                    <?php if ($teams): ?>
                        <div class="rel-char-teams">
                            <?php foreach ($teams as $t): ?>
                                <span class="rel-team-tag"
                                      style="--c: <?= e($t['color'] ?: '#888') ?>;"><?= e($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="rel-char-actions">
                    <a class="btn-secondary btn-sm" href="admin.php?action=character_edit&id=<?= (int)$c['id'] ?>">编辑</a>
                    <form method="post" action="admin.php?action=character_delete" style="display:inline;"
                          onsubmit="return confirm('删除角色会连带删除所有关系，确认？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn-danger btn-sm">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="?action=characters&q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">← 上一页</a>
        <?php else: ?>
            <span class="disabled">← 上一页</span>
        <?php endif; ?>
        <span class="page-info">第 <?= $page ?> / <?= $totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
            <a href="?action=characters&q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">下一页 →</a>
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