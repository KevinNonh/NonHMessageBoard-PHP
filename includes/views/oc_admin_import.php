<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>批量导入</title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="oc.css">
</head>
<body class="page-admin">

<header class="topbar">
    <div class="brand">批量导入</div>
    <div class="topbar-actions">
        <a class="btn-icon" href="oc_admin.php" title="返回">←</a>
    </div>
</header>

<main class="admin-main admin-narrow">
    <section class="settings-section">
        <h2>粘贴 JSON 数据</h2>

        <?php if (!empty($importErrors)): ?>
            <div class="alert alert-error">
                <?php foreach ($importErrors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="settings-hint" style="margin-top:0;">
            格式示例：
            <pre style="background: var(--bg-soft); padding: 10px; border-radius: 6px; overflow: auto; font-size: 12px; line-height: 1.5;">[
  {"name": "小狐狸", "month": 4, "day": 17, "color": "#ff7aab"},
  {"name": "白兔", "month": 7, "day": 3, "color": "#b0d8ff", "year": 2021},
  {"name": "阿龙", "month": 12, "day": 25, "color": "#ffb066", "wiki": "https://..."}
]</pre>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <label>
                <span>JSON</span>
                <textarea name="json" rows="14" required placeholder="粘贴 JSON 数组..."><?= e($_POST['json'] ?? '') ?></textarea>
            </label>
            <div class="form-actions">
                <a class="btn-secondary" href="oc_admin.php">取消</a>
                <button type="submit" class="btn-primary">导入</button>
            </div>
        </form>
    </section>
</main>

</body>
</html>