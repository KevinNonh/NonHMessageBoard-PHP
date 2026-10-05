<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(setting('site_name', '留言板')) ?></title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="style.css">
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
    <div class="brand">留言管理</div>
    <div class="topbar-actions">
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
        <a class="btn-icon" href="admin.php?action=settings" title="站点设置">⚙️</a>
		<a class="btn-icon" href="admin.php?action=download_db" title="下载数据库备份">💾</a>
        <a class="btn-icon" href="index.php" title="前台">🏠</a>
        <a class="btn-icon" href="admin.php?action=logout" title="退出">🚪</a>
</div>
</header>

<main class="admin-main">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <?php $back = 'filter=' . urlencode($filter) . '&page=' . $page; ?>

    <div class="filter-bar">
        <?php
            $tabs = [
                'all' => '全部',
                'pending' => '未回复',
                'replied' => '已回复',
                'private' => '仅管理员可见',
            ];
            foreach ($tabs as $k => $label):
                $active = $filter === $k ? 'active' : '';
        ?>
            <a class="filter-tab <?= $active ?>" href="admin.php?filter=<?= e($k) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <span class="filter-count">共 <?= (int)$total ?> 条</span>
    </div>

    <?php if (!$messages): ?>
        <div class="empty-hint">暂无留言。</div>
    <?php endif; ?>

    <div class="msg-list">
        <?php foreach ($messages as $m): ?>
            <article class="msg-card" data-id="<?= (int)$m['id'] ?>">
    <header class="msg-head">
        <div class="msg-meta">
            <strong><?= e($m['nickname']) ?></strong>
            <span class="muted">&lt;<?= e($m['email']) ?>&gt;</span>
            <?php if (!empty($m['website'])): ?>
                <a href="<?= e($m['website']) ?>" target="_blank" rel="noopener nofollow"><?= e($m['website']) ?></a>
            <?php endif; ?>
            <?php if ((int)$m['is_private'] === 1): ?>
                <span class="tag tag-private">仅管理员可见</span>
            <?php endif; ?>
            <?php if (!empty($m['reply'])): ?>
                <span class="tag tag-replied">已回复</span>
            <?php else: ?>
                <span class="tag tag-pending">未回复</span>
            <?php endif; ?>
        </div>
        <div class="msg-time">
            #<?= (int)$m['id'] ?> · <?= e($m['created_at']) ?>
            <?php if (!empty($m['ip'])): ?> · <?= e($m['ip']) ?><?php endif; ?>
        </div>
    </header>

    <!-- 只读视图 -->
    <div class="msg-view">
        <div class="msg-content"><?= nl2br(e($m['content'])) ?></div>
        <div class="msg-view-actions">
            <button type="button" class="btn-secondary btn-sm" data-edit-toggle>编辑</button>
        </div>
    </div>

    <!-- 编辑视图 -->
    <div class="msg-edit" hidden>
        <form method="post" action="admin.php?action=edit" class="edit-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="back" value="<?= e($back) ?>">

            <div class="edit-grid">
                <label>
                    <span>昵称 *</span>
                    <input type="text" name="nickname" maxlength="30" required
                           value="<?= e($m['nickname']) ?>">
                </label>
                <label>
                    <span>邮箱 *</span>
                    <input type="email" name="email" maxlength="100" required
                           value="<?= e($m['email']) ?>">
                </label>
            </div>

            <label>
                <span>网址（选填）</span>
                <input type="text" name="website" maxlength="200"
                       value="<?= e($m['website'] ?? '') ?>">
            </label>

            <label>
                <span>内容 *</span>
                <textarea name="content" rows="5" maxlength="1000" required><?= e($m['content']) ?></textarea>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="is_private" value="1"
                       <?= (int)$m['is_private'] === 1 ? 'checked' : '' ?>>
                <span>仅管理员可见</span>
            </label>

            <div class="msg-edit-actions">
                <button type="button" class="btn-secondary btn-sm" data-edit-cancel>取消</button>
                <button type="submit" class="btn-primary btn-sm">保存修改</button>
            </div>
        </form>
    </div>

    <form method="post" action="admin.php?action=reply" class="reply-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <textarea name="reply" rows="2" maxlength="1000"
                  placeholder="回复这条留言（留空则清除回复）"><?= e($m['reply'] ?? '') ?></textarea>
        <div class="reply-actions">
            <button type="submit" class="btn-primary btn-sm">保存回复</button>
        </div>
    </form>

    <form method="post" action="admin.php?action=delete" class="delete-form"
          onsubmit="return confirm('确认删除这条留言？此操作不可恢复。');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <button type="submit" class="btn-danger btn-sm">删除</button>
    </form>
</article>
        <?php endforeach; ?>
    </div>
	
	<?php if ($totalPages > 1): ?>
	<nav class="pagination">
	    <?php if ($page > 1): ?>
	        <a href="admin.php?filter=<?= e($filter) ?>&page=<?= $page - 1 ?>">← 上一页</a>
	    <?php else: ?>
	        <span class="disabled">← 上一页</span>
	    <?php endif; ?>
	
	    <span class="page-info">第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页</span>
	
	    <?php if ($page < $totalPages): ?>
	        <a href="admin.php?filter=<?= e($filter) ?>&page=<?= $page + 1 ?>">下一页 →</a>
	    <?php else: ?>
	        <span class="disabled">下一页 →</span>
	    <?php endif; ?>
</nav>
<?php endif; ?>
</main>

<script src="app.js"></script>
</body>
</html>