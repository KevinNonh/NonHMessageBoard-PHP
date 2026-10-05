<?php
$pageTitle = $pageTitle ?? '关系图';
$favicon = site_favicon_url();

// SCRIPT_NAME 形如 /public/relations/admin.php
$scriptDir = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
// 往上一级，得到 /public
$parentDir = rtrim(dirname($scriptDir), '/\\');
if ($parentDir === '/' || $parentDir === '\\' || $parentDir === '.') {
    $parentDir = '';
}
if ($scriptDir === '' || $scriptDir === '.') {
    $scriptDir = '';
}

$mainCss = $parentDir . '/style.css';   // → /public/style.css
$relCss  = $scriptDir . '/style.css';   // → /public/relations/style.css
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e($mainCss) ?>">
<link rel="stylesheet" href="<?= e($relCss) ?>">
<script>
(function () {
    try {
        var t = localStorage.getItem('mb_theme');
        if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
    } catch (e) {}
})();
</script>