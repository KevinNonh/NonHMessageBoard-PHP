<?php
$flash = flash_get();
$timezones = [
    'Asia/Shanghai'      => '中国标准时间 (UTC+8)',
    'Asia/Hong_Kong'     => '香港时间 (UTC+8)',
    'Asia/Taipei'        => '台北时间 (UTC+8)',
    'Asia/Singapore'     => '新加坡时间 (UTC+8)',
    'Asia/Tokyo'         => '日本时间 (UTC+9)',
    'Asia/Seoul'         => '韩国时间 (UTC+9)',
    'Europe/London'      => '伦敦时间',
    'Europe/Paris'       => '巴黎时间',
    'America/New_York'   => '纽约时间',
    'America/Los_Angeles'=> '洛杉矶时间',
    'UTC'                => 'UTC（+0）',
];
$current = [
    'site_name'      => setting('site_name', '留言板'),
    'site_icon'      => setting('site_icon', '💬'),
    'timezone'       => setting('timezone', 'Asia/Shanghai'),
    'admin_username' => setting('admin_username', 'admin'),
	'announcement'   => setting('announcement', ''),
	'footer_html'    => setting('footer_html', ''),
];
if (!empty($settingOld)) {
    foreach (['site_name', 'site_icon', 'timezone', 'admin_username', 'announcement', 'footer_html'] as $k) {
        if (isset($settingOld[$k])) {
            $current[$k] = (string)$settingOld[$k];
        }
    }
}
$favicon = site_favicon_url();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>站点设置 - <?= e(setting('site_name', '留言板')) ?></title>
<?php if ($favicon): ?>
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
    <div class="brand">站点设置</div>
    <div class="topbar-actions">
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
        <a class="btn-icon" href="admin.php" title="留言管理">💬</a>
		<a class="btn-icon" href="admin.php?action=download_db" title="下载数据库备份">💾</a>
        <a class="btn-icon" href="index.php" title="前台">🏠</a>
        <a class="btn-icon" href="admin.php?action=logout" title="退出">🚪</a>
    </div>
</header>

<main class="admin-main admin-narrow">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>
	
    <?php if (!empty($settingErrors)): ?>
        <div class="alert alert-error">
            <?php foreach ($settingErrors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="admin.php?action=settings" class="settings-form">
        <?= csrf_field() ?>

        <section class="settings-section">
            <h2>站点信息</h2>
            <label>
                <span>站点名称</span>
                <input type="text" name="site_name" maxlength="50" required
                       value="<?= e($current['site_name']) ?>">
            </label>
            <label>
                <span>站点图标（Emoji 或图片 URL）</span>
                <input type="text" name="site_icon" maxlength="200"
                       placeholder="例如：💬 或 https://example.com/icon.png"
                       value="<?= e($current['site_icon']) ?>">
            </label>
            <div class="settings-hint">
                当前预览：<span class="icon-preview"><?= e($current['site_icon']) ?></span>
            </div>
            <label>
                <span>时区</span>
                <select name="timezone">
                    <?php foreach ($timezones as $tz => $label): ?>
                        <option value="<?= e($tz) ?>" <?= $current['timezone'] === $tz ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="settings-hint">修改后新留言的时间会按此显示。</div>
        </section>

        <section class="settings-section">
            <h2>前台公告</h2>
            <label>
                <span>公告内容（留空则不显示公告气泡）</span>
                <textarea name="announcement" rows="4" maxlength="500"
                          placeholder="例如：欢迎来到留言板，请文明发言 (＾▽＾)"><?= e($current['announcement']) ?></textarea>
            </label>
            <div class="settings-hint">
                前台会以醒目气泡显示，位置固定在左上角，可自由拖动。
            </div>
        </section>

        <section class="settings-section">
            <h2>页脚信息</h2>
            <label>
                <span>页脚 HTML（留空则不显示）</span>
                <textarea name="footer_html" rows="3" maxlength="2000"
                          placeholder="© 2026 我的留言板 · &lt;a href=&quot;https://beian.miit.gov.cn&quot;         target=&quot;_blank&quot;&gt;京ICP备xxxxxxxx号&lt;/a&gt;"><?= e($current['footer_html']) ?></textarea>
            </label>
            <div class="settings-hint">
                会以固定页脚显示在前台底部，支持 HTML。可写版权、备案号、友情链接等。请勿放入不可信内容。
            </div>
        </section>

        <section class="settings-section">
            <h2>管理员账号</h2>
            <label>
                <span>用户名</span>
                <input type="text" name="admin_username" maxlength="50" required
                       value="<?= e($current['admin_username']) ?>">
            </label>
            <label>
                <span>当前密码（修改密码时必填）</span>
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label>
                <span>新密码（不修改请留空）</span>
                <input type="password" name="new_password" autocomplete="new-password">
            </label>
            <label>
                <span>确认新密码</span>
                <input type="password" name="confirm_password" autocomplete="new-password">
            </label>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn-primary">保存设置</button>
        </div>
    </form>
	
	<section class="settings-section">
    	<h2>数据备份</h2>
    	<div class="settings-hint" style="margin-top:0;">
        下载当前的 SQLite 数据库文件，用于备份或迁移。恢复时请参考文档，直接替换 <code>storage/</code> 下的文件。
    	</div>
    	<div class="form-actions" style="justify-content:flex-start;">
    	    <a class="btn-primary" href="admin.php?action=download_db" style="text-decoration:none;">💾 下载数据库</a>
    	</div>
	</section>
	
</main>

<script src="app.js"></script>
</body>
</html>