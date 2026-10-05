<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$styles = [
    'default' => '默认',
    'cute'    => '🌸 可爱',
    'tech'    => '⚡ 科技',
    'green'   => '🌿 护眼绿',
    'star'    => '🌌 星空',
    'snow'    => '❄️ 雪夜',
    'cyber'   => '⚡ 赛博朋克',
    'aurora'  => '🔮 极光',
    'y2k'     => '💾 千禧年',
];
$themes = [
    'light' => '浅色',
    'dark'  => '深色',
];

// 当前站点绝对地址前缀
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$origin = $scheme . '://' . $host . $basePath;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OC 嵌入预览</title>
<?php $favicon = site_favicon_url(); if ($favicon): ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                     "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
        background: #eef1f6;
        color: #222;
    }
    header.top {
        position: sticky;
        top: 0;
        z-index: 10;
        background: rgba(255,255,255,.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid #dde2ea;
        padding: 14px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    header.top h1 {
        font-size: 15px;
        margin: 0;
        font-weight: 600;
    }
    header.top .actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    header.top a {
        font-size: 13px;
        color: #4a6cf7;
        text-decoration: none;
    }
    header.top a:hover { text-decoration: underline; }

    .grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        padding: 24px 40px 60px;
    }
    @media (max-width: 900px) {
        .grid { grid-template-columns: 1fr; padding: 20px 20px 40px; }
        header.top { padding: 14px 20px; }
    }

    .cell {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(20, 30, 60, .08);
        display: flex;
        flex-direction: column;
    }
    .cell-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid #eef1f5;
        gap: 10px;
    }
    .cell-title {
        font-size: 13px;
        font-family: ui-monospace, Menlo, Consolas, monospace;
        color: #333;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cell-title .style-name {
        font-family: inherit;
        color: #111;
        font-weight: 600;
        margin-right: 6px;
    }
    .cell-title .theme-name {
        color: #6b7d8f;
    }
    .btn-copy {
        flex-shrink: 0;
        padding: 5px 12px;
        font: inherit;
        font-size: 12px;
        border-radius: 6px;
        border: 1px solid #cfd6e0;
        background: #fff;
        color: #4a6cf7;
        cursor: pointer;
        transition: all .15s;
    }
    .btn-copy:hover {
        border-color: #4a6cf7;
        background: #f5f7ff;
    }
    .btn-copy.copied {
        background: #4a6cf7;
        border-color: #4a6cf7;
        color: #fff;
    }
    iframe {
        width: 100%;
        height: 500px;
        border: 0;
        display: block;
        background: #fafafa;
    }
</style>
</head>
<body>

<header class="top">
    <h1>🎂 OC 嵌入预览 · 共 <?= count($styles) * count($themes) ?> 种组合</h1>
    <div class="actions">
        <a href="oc.php" target="_blank">查看前台 →</a>
        <a href="oc_admin.php">OC 后台</a>
        <a href="admin.php">留言板后台</a>
    </div>
</header>

<div class="grid">
<?php foreach ($styles as $sKey => $sLabel): ?>
    <?php foreach ($themes as $tKey => $tLabel): ?>
        <?php
            $url = $origin . '/oc.php?embed=1&style=' . $sKey . '&theme=' . $tKey;
        ?>
        <div class="cell">
            <div class="cell-head">
                <div class="cell-title">
                    <span class="style-name"><?= e($sLabel) ?></span>
                    <span class="theme-name">/ <?= e($tLabel) ?></span>
                </div>
                <button type="button" class="btn-copy"
                        data-url="<?= e($url) ?>"
                        data-original="复制链接">复制链接</button>
            </div>
            <iframe src="<?= e($url) ?>" loading="lazy"></iframe>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>
</div>

<script>
(function () {
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    document.querySelectorAll('.btn-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.dataset.url || '';
            copyText(url).then(function () {
                var orig = btn.dataset.original || '复制链接';
                btn.textContent = '✓ 已复制';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = orig;
                    btn.classList.remove('copied');
                }, 1500);
            }).catch(function () {
                btn.textContent = '复制失败';
                setTimeout(function () {
                    btn.textContent = btn.dataset.original || '复制链接';
                }, 1500);
            });
        });
    });
})();
</script>

</body>
</html>