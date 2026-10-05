<!DOCTYPE html>
<?php
$forceTheme = (string)($_GET['theme'] ?? '');
$forceStyle = (string)($_GET['style'] ?? '');
?>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?= e(setting('site_name', '留言板')) ?></title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="style.css">
<script>
(function () {
    try {
        <?php if (in_array($forceTheme, ['light', 'dark'], true)): ?>
        document.documentElement.dataset.theme = <?= json_encode($forceTheme) ?>;
        <?php else: ?>
        var t = localStorage.getItem('mb_theme');
        if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
        <?php endif; ?>

        <?php if ($forceStyle !== ''): ?>
        document.documentElement.dataset.style = <?= json_encode($forceStyle) ?>;
        <?php else: ?>
        var s = localStorage.getItem('mb_style');
        var allowed = ['cute','tech','green','star','snow','cyber','aurora','y2k'];
        if (s && allowed.indexOf(s) !== -1) {
            document.documentElement.dataset.style = s;
        }
        <?php endif; ?>
    } catch (e) {}
})();
</script>
</head>
<body class="page-home" data-highlight="<?= max(0, (int)($_GET['highlight'] ?? 0)) ?>">

<header class="topbar">
    <div class="brand"><?= e($config['site_name']) ?></div>
	<div class="style-picker">
    <button id="style-toggle" class="btn-icon" type="button" title="切换风格">🎨</button>
    <div id="style-menu" class="style-menu" hidden>
        <div class="style-menu-title">选择风格</div>
        <button class="style-item" type="button" data-style-option="default">
            <span class="style-dot style-dot-default"></span><span>默认</span>
        </button>
        <button class="style-item" type="button" data-style-option="cute">
            <span class="style-dot style-dot-cute"></span><span>🌸 可爱风</span>
        </button>
        <button class="style-item" type="button" data-style-option="tech">
            <span class="style-dot style-dot-tech"></span><span>⚡ 科技风</span>
        </button>
        <button class="style-item" type="button" data-style-option="green">
            <span class="style-dot style-dot-green"></span><span>🌿 护眼绿</span>
        </button>
		<button class="style-item" type="button" data-style-option="star">
		    <span class="style-dot style-dot-star"></span><span>🌌 星空</span>
		</button>
		<button class="style-item" type="button" data-style-option="snow">
		    <span class="style-dot style-dot-snow"></span><span>❄️ 雪夜</span>
		</button>
		<button class="style-item" type="button" data-style-option="cyber">
		    <span class="style-dot style-dot-cyber"></span><span>⚡ 赛博朋克</span>
		</button>
		<button class="style-item" type="button" data-style-option="aurora">
		    <span class="style-dot style-dot-aurora"></span><span>🔮 极光</span>
		</button>
		<button class="style-item" type="button" data-style-option="y2k">
		    <span class="style-dot style-dot-y2k"></span><span>💾 千禧年</span>
		</button>
    </div>
</div>
    <div class="topbar-actions">
	    <button id="view-toggle" class="btn-icon" type="button" title="切换到列表视图">📋</button>
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
        <a class="btn-icon" href="admin.php" title="后台">⚙️</a>
    </div>
</header>

<canvas id="bg-canvas" class="bg-canvas"></canvas>

<main id="bubble-field" class="bubble-field">

    <?php $announcement = setting('announcement', ''); ?>
    <?php if ($announcement !== ''): ?>
        <div class="bubble bubble-announcement" data-id="announcement" data-announcement="1">
            <div class="bubble-head">
                <span class="bubble-nick">📢 公告</span>
            </div>
            <div class="bubble-content">
                <div class="bubble-clip"><?= e($announcement) ?></div>
                <div class="bubble-full">
                    <div class="full-text"><?= nl2br(e($announcement)) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($messages as $m): ?>
        <div class="bubble" data-id="<?= (int)$m['id'] ?>">
            <div class="bubble-head">
                <span class="bubble-nick">
                    <?php if (!empty($m['website'])): ?>
                        <a href="<?= e($m['website']) ?>" target="_blank" rel="noopener nofollow"><?= e($m['nickname']) ?></a>
                    <?php else: ?>
                        <?= e($m['nickname']) ?>
                    <?php endif; ?>
                </span>
                <?php if (!empty($m['reply'])): ?>
                    <span class="bubble-check" title="管理员已回复">✓</span>
                <?php endif; ?>
            </div>
            <div class="bubble-content">
                <div class="bubble-clip"><?= e($m['content']) ?></div>
                <div class="bubble-full">
                    <div class="full-text"><?= nl2br(e($m['content'])) ?></div>
                    <?php if (!empty($m['reply'])): ?>
                        <div class="full-reply">
                            <div class="reply-label">管理员回复</div>
                            <div class="reply-text"><?= nl2br(e($m['reply'])) ?></div>
                            <?php if (!empty($m['replied_at'])): ?>
                                <div class="reply-time"><?= e($m['replied_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="full-time"><?= e($m['created_at']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$messages): ?>
        <div class="empty-hint">还没有留言，点右下角按钮留下第一条吧。</div>
    <?php endif; ?>
</main>

<div id="list-view" class="list-view">
    <div class="list-loading">加载中…</div>
</div>

<button id="open-post" class="fab" type="button" title="留言">✏️</button>

<?php if ($hasMore): ?>
<div id="load-more-wrap" class="load-more-wrap">
    <button id="load-more" class="load-more-btn" type="button">加载更多留言</button>
</div>
<?php endif; ?>

<?php $footerHtml = setting('footer_html', ''); ?>
<?php if ($footerHtml !== ''): ?>
    <footer class="page-footer"><?= $footerHtml ?></footer>
<?php endif; ?>

<button id="open-post" class="fab" type="button" title="留言">✏️</button>

<div id="post-modal" class="modal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-panel">
        <div class="modal-head">
            <h2>留下你的留言</h2>
            <button class="btn-icon" type="button" data-close>✕</button>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php" class="post-form">
            <?= csrf_field() ?>
            <input type="text" name="hp" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            <label>
                <span>昵称 *</span>
                <input type="text" name="nickname" maxlength="30" required
                       value="<?= e($old['nickname'] ?? '') ?>">
            </label>

            <label>
                <span>邮箱 *</span>
                <input type="email" name="email" maxlength="100" required
                       value="<?= e($old['email'] ?? '') ?>">
            </label>

            <label>
                <span>网址（选填）</span>
                <input type="text" name="website" maxlength="200"
                       value="<?= e($old['website'] ?? '') ?>">
            </label>

            <label>
                <span>留言内容 *</span>
                <textarea name="content" rows="5" maxlength="1000" required><?= e($old['content'] ?? '') ?></textarea>
            </label>

            <div class="kaomoji-bar">
                <?php foreach (['(- v -)', '(＾▽＾)', '(T_T)', '(￣▽￣)', '(´･ω･`)', '(≧▽≦)', '(・_・;)'] as $k): ?>
                    <button type="button" class="kaomoji" data-k="<?= e($k) ?>"><?= e($k) ?></button>
                <?php endforeach; ?>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_private" value="1"
                       <?= !empty($old['private']) ? 'checked' : '' ?>>
                <span>仅管理员可见</span>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn-primary">发布留言</button>
            </div>
        </form>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>