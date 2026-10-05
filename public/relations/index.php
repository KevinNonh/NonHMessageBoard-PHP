<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/relations/bootstrap.php';

$favicon = site_favicon_url();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(rel_page_title()) ?></title>
<?php if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="style.css">
<script>
(function () {
    try {
        var t = localStorage.getItem('mb_theme');
        if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
        var s = localStorage.getItem('mb_style');
        var allowed = ['cute','tech','green','star','snow','cyber','aurora','y2k'];
        if (s && allowed.indexOf(s) !== -1) document.documentElement.dataset.style = s;
    } catch (e) {}
})();
</script>
</head>
<body class="rel-page <?= is_admin() ? '' : 'rel-readonly' ?>">

<canvas id="bg-canvas" class="bg-canvas"></canvas>

<header class="topbar">
    <div class="brand">🕸 关系图</div>
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
	<button id="rel-help-btn" class="btn-icon" type="button" title="快捷键说明 (H)">❓</button>
    <a class="btn-icon" href="../index.php" title="留言板">💬</a>
    <a class="btn-icon" href="admin.php" title="管理">⚙️</a>
</div>
</header>

<div class="rel-app">

    <aside class="rel-sidebar" id="rel-sidebar">
        <div class="rel-sidebar-head">
            <input type="search" id="rel-search" placeholder="搜索角色…" autocomplete="off">
            <button type="button" class="rel-clear" id="rel-clear" hidden>✕</button>
        </div>

        <div class="rel-sidebar-filters">
            <div class="rel-filter-head">
                <span>团队</span>
                <button type="button" class="rel-filter-toggle" data-target="rel-filter-teams">展开</button>
            </div>
            <div class="rel-filter-list" id="rel-filter-teams" hidden></div>

            <div class="rel-filter-head">
                <span>关系类型</span>
                <button type="button" class="rel-filter-toggle" data-target="rel-filter-types">展开</button>
            </div>
            <div class="rel-filter-list" id="rel-filter-types" hidden></div>
        </div>

        <div class="rel-sidebar-body" id="rel-sidebar-body">
            <div class="rel-sidebar-hint">点击一个节点查看详情</div>
        </div>
    </aside>

    <div class="rel-canvas-wrap" id="rel-canvas-wrap">
        <canvas id="rel-canvas"></canvas>

        <div class="rel-canvas-controls">
            <button type="button" class="btn-icon" id="rel-zoom-in" title="放大">＋</button>
            <button type="button" class="btn-icon" id="rel-zoom-out" title="缩小">－</button>
            <button type="button" class="btn-icon" id="rel-zoom-reset" title="重置视图">⟲</button>
			<button type="button" class="btn-icon" id="rel-select-mode" title="选择模式（框选多个节点）">▢</button>
            <button type="button" class="btn-icon" id="rel-export-btn" title="导出图片">📷</button>
        
            <div class="rel-export-panel" id="rel-export-panel" hidden>
                <div class="rel-export-title">导出图片</div>
				<label class="rel-export-row">
   					 <span>范围</span>
   					 <select id="rel-export-scope">
   					     <option value="viewport">当前视图</option>
   					     <option value="full">整张图（高分辨率）</option>
   					 </select>
				</label>
                <label class="rel-export-row">
                    <span>背景</span>
                    <select id="rel-export-bg">
                        <option value="theme">跟随主题</option>
                        <option value="white">纯白</option>
                        <option value="black">纯黑</option>
                        <option value="transparent">透明</option>
                    </select>
                </label>
                <label class="rel-export-row">
                    <span>分辨率</span>
                    <select id="rel-export-scale">
                        <option value="1">1×</option>
                        <option value="2" selected>2×</option>
                        <option value="3">3×</option>
                    </select>
                </label>
				<label class="rel-export-row rel-export-check">
 				   <input type="checkbox" id="rel-export-bg-effects" checked>
 				   <span>包含背景特效</span>
				</label>
                <div class="rel-export-actions">
                    <button type="button" class="btn-secondary btn-sm" id="rel-export-cancel">取消</button>
                    <button type="button" class="btn-primary btn-sm" id="rel-export-confirm">下载</button>
                </div>
            </div>
        </div>

        <button type="button" class="rel-sidebar-toggle" id="rel-sidebar-toggle" title="侧栏">☰</button>

        <div class="rel-canvas-legend" id="rel-legend" hidden></div>
        <div class="rel-canvas-loading" id="rel-loading">加载中…</div>
    </div>

</div>

<script>
window.REL_API_TOKEN = <?= json_encode(rel_api_token()) ?>;
window.REL_CAN_EDIT = <?= is_admin() ? 'true' : 'false' ?>;
</script>
<script src="app.js"></script>
</body>
</html>