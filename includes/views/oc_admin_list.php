<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OC 管理</title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="oc.css">
<script>
(function () {
    try {
        var t = localStorage.getItem('mb_theme');
        if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
    } catch (e) {}
})();
</script>
</head>
<body class="page-admin">

<header class="topbar">
    <div class="brand">🎂 OC 管理</div>
    <div class="topbar-actions">
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
        <a class="btn-icon" href="oc.php" target="_blank" title="查看前台">👁</a>
        <a class="btn-icon" href="admin.php" title="留言板后台">💬</a>
        <a class="btn-icon" href="admin.php?action=logout" title="退出">🚪</a>
    </div>
</header>

<main class="admin-main">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="oc-admin-toolbar">
        <a class="btn-primary btn-sm" href="oc_admin.php?action=new">+ 新增 OC</a>
        <a class="btn-secondary btn-sm" href="oc_admin.php?action=import">批量导入</a>
        <a class="btn-secondary btn-sm" href="oc_admin.php?action=export">导出 JSON</a>

        <form method="get" action="oc_admin.php" class="oc-admin-search">
            <input type="text" name="q" placeholder="搜索名字" value="<?= e($search) ?>">
            <button type="submit" class="btn-secondary btn-sm">搜索</button>
        </form>
    </div>

    <div class="oc-admin-count">共 <?= (int)$total ?> 个 OC</div>

    <?php if (!$rows): ?>
        <div class="empty-hint">还没有 OC，点击「新增 OC」或「批量导入」开始吧。</div>
    <?php else: ?>
    <div class="oc-admin-list">
        <?php foreach ($rows as $oc): ?>
            <div class="oc-admin-row">
                <div class="oc-avatar"
                     style="--oc-color: <?= e($oc['color']) ?>;"
                     <?= empty($oc['avatar_url']) ? 'data-initial="' . e(mb_substr($oc['name'], 0, 1)) . '"' : '' ?>>
                    <?php if (!empty($oc['avatar_url'])): ?>
                        <img src="<?= e($oc['avatar_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <?php endif; ?>
                </div>
                <div class="oc-admin-info">
                    <div class="oc-admin-name"><?= e($oc['name']) ?></div>
                    <div class="oc-admin-meta">
                        <?= (int)$oc['birth_month'] ?> 月 <?= (int)$oc['birth_day'] ?> 日
                        <?php if ($oc['birth_year']): ?> · <?= (int)$oc['birth_year'] ?> 年<?php endif; ?>
                    </div>
                </div>
                <div class="oc-admin-actions">
                    <a class="btn-secondary btn-sm" href="oc_admin.php?action=edit&id=<?= (int)$oc['id'] ?>">编辑</a>
                    <form method="post" action="oc_admin.php?action=delete" style="display:inline;"
                          onsubmit="return confirm('确认删除？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$oc['id'] ?>">
                        <button type="submit" class="btn-danger btn-sm">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= e(http_build_query(['q' => $search, 'page' => $page - 1])) ?>">← 上一页</a>
        <?php else: ?>
            <span class="disabled">← 上一页</span>
        <?php endif; ?>
        <span class="page-info">第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= e(http_build_query(['q' => $search, 'page' => $page + 1])) ?>">下一页 →</a>
        <?php else: ?>
            <span class="disabled">下一页 →</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

</main>

<script src="app.js"></script>
</body>
</html>