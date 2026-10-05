<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $action === 'new' ? '新增 OC' : '编辑 OC' ?></title>
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
    <div class="brand"><?= $action === 'new' ? '新增 OC' : '编辑 OC' ?></div>
    <div class="topbar-actions">
        <a class="btn-icon" href="oc_admin.php" title="返回列表">←</a>
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
    </div>
</header>

<main class="admin-main admin-narrow">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="settings-form">
        <?= csrf_field() ?>

        <section class="settings-section">
            <h2>基本信息</h2>
            <label>
                <span>名字 *</span>
                <input type="text" name="name" maxlength="50" required
                       value="<?= e($_POST['name'] ?? $oc['name'] ?? '') ?>">
            </label>
            <div class="edit-grid">
                <label>
                    <span>月份 *</span>
                    <input type="number" name="birth_month" min="1" max="12" required
                           value="<?= e($_POST['birth_month'] ?? $oc['birth_month'] ?? '') ?>">
                </label>
                <label>
                    <span>日期 *</span>
                    <input type="number" name="birth_day" min="1" max="31" required
                           value="<?= e($_POST['birth_day'] ?? $oc['birth_day'] ?? '') ?>">
                </label>
            </div>
            <label>
                <span>出生年份（选填）</span>
                <input type="number" name="birth_year" min="1" max="9999"
                       value="<?= e($_POST['birth_year'] ?? $oc['birth_year'] ?? '') ?>">
            </label>
        </section>

        <section class="settings-section">
            <h2>外观</h2>
            <label>
                <span>代表色 *（#RRGGBB）</span>
                <input type="text" name="color" maxlength="20" required
                       pattern="^#[0-9a-fA-F]{3,8}$" placeholder="#ff7aab"
                       value="<?= e($_POST['color'] ?? $oc['color'] ?? '#ff7aab') ?>">
            </label>
            <label>
                <span>头像 URL（选填）</span>
                <input type="url" name="avatar_url" maxlength="500" placeholder="https://..."
                       value="<?= e($_POST['avatar_url'] ?? $oc['avatar_url'] ?? '') ?>">
            </label>
            <div class="settings-hint">不填则用代表色 + 名字首字填充。</div>
        </section>

        <section class="settings-section">
            <h2>链接</h2>
            <label>
                <span>Wiki URL（选填）</span>
                <input type="url" name="wiki_url" maxlength="500" placeholder="https://..."
                       value="<?= e($_POST['wiki_url'] ?? $oc['wiki_url'] ?? '') ?>">
            </label>
            <div class="settings-hint">留空则前台不显示「查看详情」按钮。</div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="oc_admin.php">取消</a>
            <button type="submit" class="btn-primary">保存</button>
        </div>
    </form>

</main>

<script src="app.js"></script>
</body>
</html>