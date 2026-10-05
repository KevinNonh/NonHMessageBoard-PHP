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
<body class="page-login">

<div class="login-box">
    <div class="login-head">
        <h1>后台登录</h1>
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
    </div>

    <?php if (!empty($loginError)): ?>
        <div class="alert alert-error"><?= e($loginError) ?></div>
    <?php endif; ?>

    <form method="post" action="admin.php?action=login">
        <?= csrf_field() ?>
        <label>
            <span>用户名</span>
            <input type="text" name="username" required autofocus>
        </label>
        <label>
            <span>密码</span>
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn-primary">登录</button>
    </form>

    <div class="login-foot">
        <a href="index.php">← 返回留言板</a>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>