<?php
$pageTitle = '关系图管理';
$charCount  = (int)$pdo->query('SELECT COUNT(*) FROM rel_characters')->fetchColumn();
$teamCount  = (int)$pdo->query('SELECT COUNT(*) FROM rel_teams')->fetchColumn();
$typeCount  = (int)$pdo->query('SELECT COUNT(*) FROM rel_relation_types')->fetchColumn();
$relCount   = (int)$pdo->query('SELECT COUNT(*) FROM rel_relations')->fetchColumn();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="page-admin">
<?php require __DIR__ . '/_topbar.php'; ?>

<main class="admin-main">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="rel-stats">
        <a class="rel-stat" href="admin.php?action=characters">
            <div class="rel-stat-num"><?= $charCount ?></div>
            <div class="rel-stat-label">角色</div>
        </a>
        <a class="rel-stat" href="admin.php?action=teams">
            <div class="rel-stat-num"><?= $teamCount ?></div>
            <div class="rel-stat-label">团队</div>
        </a>
        <a class="rel-stat" href="admin.php?action=types">
            <div class="rel-stat-num"><?= $typeCount ?></div>
            <div class="rel-stat-label">关系类型</div>
        </a>
        <a class="rel-stat" href="admin.php?action=relations">
            <div class="rel-stat-num"><?= $relCount ?></div>
            <div class="rel-stat-label">关系</div>
        </a>
    </div>

    <div class="rel-quick">
        <a class="btn-primary" href="admin.php?action=character_edit">+ 新建角色</a>
        <a class="btn-secondary" href="admin.php?action=relation_edit">+ 新建关系</a>
		<a class="btn-secondary" href="admin.php?action=import">📥 导入 / 导出</a>
    </div>
	
	<section class="settings-section" style="margin-top:20px;">
    <h2>前台设置</h2>
    <form method="post" action="admin.php?action=save_title" class="rel-title-form">
        <?= csrf_field() ?>
        <label class="rel-title-label">
            <span>前台网页标题</span>
            <input type="text" name="page_title" maxlength="60"
                   value="<?= e(setting('rel_page_title', '关系图')) ?>"
                   placeholder="关系图">
        </label>
        <button type="submit" class="btn-primary btn-sm">保存</button>
    </form>
    <div class="settings-hint">显示在浏览器标签页上。留空则恢复默认「关系图」。</div>
    </section>
	
	<section class="settings-section" style="margin-top:20px;">
    <h2>API Token</h2>
    <div class="settings-hint" style="margin-top:0;">
        前台图通过这个 token 访问 API。已登录管理员无需 token。如果 token 泄露，点右边按钮重新生成，旧 token 立刻失效。
    </div>
    <div class="rel-token-row">
        <input type="text" id="rel-token-value" readonly value="<?= e(rel_api_token()) ?>">
        <button type="button" class="btn-secondary btn-sm" id="rel-token-copy">复制</button>
        <form method="post" action="admin.php?action=regenerate_token" style="display:inline;"
              onsubmit="return confirm('重新生成 token 后，任何使用旧 token 的地方都会失效。确认？');">
            <?= csrf_field() ?>
            <button type="submit" class="btn-danger btn-sm">重新生成</button>
        </form>
    </div>
</section>

<script>
(function () {
    var btn = document.getElementById('rel-token-copy');
    var input = document.getElementById('rel-token-value');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        input.select();
        input.setSelectionRange(0, 99999);
        var text = input.value;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = '✓ 已复制';
                setTimeout(function () { btn.textContent = '复制'; }, 1500);
            });
        } else {
            try {
                document.execCommand('copy');
                btn.textContent = '✓ 已复制';
                setTimeout(function () { btn.textContent = '复制'; }, 1500);
            } catch (e) {}
        }
    });
})();
</script>
</main>

<script src="../app.js"></script>
</body>
</html>