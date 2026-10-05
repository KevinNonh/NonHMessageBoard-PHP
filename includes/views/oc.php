<?php
function oc_card_html(array $oc, bool $isToday = false): string
{
    $avatar = !empty($oc['avatar_url']) ? e($oc['avatar_url']) : '';
    $wiki = !empty($oc['wiki_url']) ? e($oc['wiki_url']) : '';
    $initial = mb_substr($oc['name'], 0, 1);
    $birthLabel = (int)$oc['birth_month'] . ' 月 ' . (int)$oc['birth_day'] . ' 日';
    if ($oc['age'] !== null) {
        $birthLabel .= ' · 满 ' . $oc['age'] . ' 岁';
    }
    $targetMs = (int)$oc['next_ts'] * 1000;
    $classes = 'oc-card' . ($isToday ? ' oc-card-today' : '');

    ob_start();
    ?>
    <article class="<?= $classes ?>"
         data-oc-target="<?= $targetMs ?>"
         data-oc-today="<?= $isToday ? '1' : '0' ?>"
         data-oc-name="<?= e($oc['name']) ?>"
         style="--oc-color: <?= e($oc['color']) ?>">
        <div class="oc-avatar"<?= $avatar ? '' : ' data-initial="' . e($initial) . '"' ?>>
            <?php if ($avatar): ?>
                <img src="<?= $avatar ?>" alt="<?= e($oc['name']) ?>" loading="lazy" referrerpolicy="no-referrer">
            <?php endif; ?>
        </div>
        <div class="oc-body">
            <div class="oc-name"><?= e($oc['name']) ?></div>
            <div class="oc-birth"><?= e($birthLabel) ?></div>
            <div class="oc-countdown">
                <?php if ($isToday): ?>
                    <span class="oc-countdown-today">🎉 今天生日</span>
                <?php else: ?>
                    <span class="oc-countdown-days">—</span>
                    <span class="oc-countdown-clock">--:--:--</span>
                <?php endif; ?>
            </div>
            <?php if ($wiki): ?>
                <a class="oc-wiki" href="<?= $wiki ?>" target="_blank" rel="noopener">查看详情 →</a>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>诺惶的OC生日倒计时 - <?= e(setting('site_name', '留言板')) ?></title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="oc.css">
<script>
(function () {
    try {
        <?php if ($embed && in_array($forceTheme, ['light', 'dark'], true)): ?>
        document.documentElement.dataset.theme = <?= json_encode($forceTheme) ?>;
        <?php else: ?>
        var t = localStorage.getItem('mb_theme');
        if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
        <?php endif; ?>

        <?php if ($embed && $forceStyle !== ''): ?>
        document.documentElement.dataset.style = <?= json_encode($forceStyle) ?>;
        <?php else: ?>
        var s = localStorage.getItem('mb_style');
        var allowed = ['cute','tech','green','star','snow','cyber','aurora','y2k'];
        if (s && allowed.indexOf(s) !== -1) document.documentElement.dataset.style = s;
        <?php endif; ?>
    } catch (e) {}
})();
</script>
</head>
<body class="oc-page <?= $embed ? 'oc-embed' : '' ?>">

<?php if (!$embed): ?>
<header class="topbar">
    <div class="brand">🎂 诺惶的 OC 生日倒计时</div>
    <div class="topbar-actions">
        <div class="style-picker">
            <button id="style-toggle" class="btn-icon" type="button" title="切换风格">🎨</button>
            <div id="style-menu" class="style-menu" hidden>
                <div class="style-menu-title">选择风格</div>
                <button class="style-item" type="button" data-style-option="default"><span class="style-dot style-dot-default"></span><span>默认</span></button>
                <button class="style-item" type="button" data-style-option="cute"><span class="style-dot style-dot-cute"></span><span>🌸 可爱风</span></button>
                <button class="style-item" type="button" data-style-option="tech"><span class="style-dot style-dot-tech"></span><span>⚡ 科技风</span></button>
                <button class="style-item" type="button" data-style-option="green"><span class="style-dot style-dot-green"></span><span>🌿 护眼绿</span></button>
                <button class="style-item" type="button" data-style-option="star"><span class="style-dot style-dot-star"></span><span>🌌 星空</span></button>
                <button class="style-item" type="button" data-style-option="snow"><span class="style-dot style-dot-snow"></span><span>❄️ 雪夜</span></button>
                <button class="style-item" type="button" data-style-option="cyber"><span class="style-dot style-dot-cyber"></span><span>⚡ 赛博朋克</span></button>
                <button class="style-item" type="button" data-style-option="aurora"><span class="style-dot style-dot-aurora"></span><span>🔮 极光</span></button>
                <button class="style-item" type="button" data-style-option="y2k"><span class="style-dot style-dot-y2k"></span><span>💾 千禧年</span></button>
            </div>
        </div>
        <button id="theme-toggle" class="btn-icon" type="button" title="切换主题">🌓</button>
        <a class="btn-icon" href="index.php" title="留言板">💬</a>
    </div>
</header>
<?php endif; ?>

<canvas id="bg-canvas" class="bg-canvas"></canvas>

<main class="oc-main">

<div class="oc-search-bar">
    <input type="search" id="oc-search" placeholder="搜索角色…" autocomplete="off">
    <button type="button" id="oc-search-clear" hidden>✕</button>
    <span id="oc-search-count" class="oc-search-count"></span>
</div>

    <?php if ($todayOcs): ?>
    <section class="oc-today-section">
        <h2 class="oc-section-title">🎉 今天生日</h2>
        <div class="oc-today-grid">
            <?php foreach ($todayOcs as $oc) echo oc_card_html($oc, true); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="oc-list-section">
        <?php if ($todayOcs && $otherOcs): ?>
            <h2 class="oc-section-title">即将到来</h2>
        <?php endif; ?>

        <?php if ($otherOcs): ?>
        <div class="oc-grid">
            <?php foreach ($otherOcs as $oc) echo oc_card_html($oc, false); ?>
        </div>
        <?php elseif (!$todayOcs): ?>
            <div class="oc-empty">还没有 OC，去后台添加吧。</div>
        <?php endif; ?>
    </section>
</main>

<script src="app.js"></script>
<script src="oc.js"></script>
</body>
</html>