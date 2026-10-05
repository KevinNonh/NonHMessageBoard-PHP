<?php
$pageTitle = $id > 0 ? '编辑关系' : '新建关系';
$allChars = rel_all_characters();
$allTypes = rel_all_types();
$flash = flash_get();

$preFrom = (int)($_GET['from_id'] ?? $_POST['from_id'] ?? $rel['from_id'] ?? 0);
$preTo   = (int)($_GET['to_id']   ?? $_POST['to_id']   ?? $rel['to_id']   ?? 0);
$preType = (int)($_POST['type_id'] ?? $rel['type_id'] ?? 0);
$preNote = (string)($_POST['note'] ?? $rel['note'] ?? '');

// 拼一份 type_id → 元数据 的 map，供 JS 回填预览用
$typeMeta = [];
foreach ($allTypes as $t) {
    $typeMeta[(int)$t['id']] = [
        'name'     => $t['name'],
        'reverse'  => $t['reverse_name'],
        'directed' => (int)$t['directed'],
        'category' => $t['category'],
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="page-admin">
<?php require __DIR__ . '/_topbar.php'; ?>

<main class="admin-main admin-narrow">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="settings-form" autocomplete="off">
        <?= csrf_field() ?>

        <section class="settings-section">
            <h2>关系设定</h2>

            <div class="edit-grid">
                <label>
                    <span>角色 A *</span>
                    <div class="rel-combo" data-combo="from">
                        <input type="text" class="rel-combo-input" placeholder="输入名字搜索..." autocomplete="off">
                        <input type="hidden" name="from_id" value="<?= $preFrom > 0 ? $preFrom : '' ?>">
                        <div class="rel-combo-menu" hidden>
                            <?php foreach ($allChars as $c): ?>
                                <div class="rel-combo-option"
                                     data-value="<?= (int)$c['id'] ?>"
                                     data-label="<?= e($c['name']) ?>"><?= e($c['name']) ?></div>
                            <?php endforeach; ?>
                            <div class="rel-combo-empty" hidden>没有匹配的角色</div>
                        </div>
                    </div>
                </label>
                <label>
                    <span>角色 B *</span>
                    <div class="rel-combo" data-combo="to">
                        <input type="text" class="rel-combo-input" placeholder="输入名字搜索..." autocomplete="off">
                        <input type="hidden" name="to_id" value="<?= $preTo > 0 ? $preTo : '' ?>">
                        <div class="rel-combo-menu" hidden>
                            <?php foreach ($allChars as $c): ?>
                                <div class="rel-combo-option"
                                     data-value="<?= (int)$c['id'] ?>"
                                     data-label="<?= e($c['name']) ?>"><?= e($c['name']) ?></div>
                            <?php endforeach; ?>
                            <div class="rel-combo-empty" hidden>没有匹配的角色</div>
                        </div>
                    </div>
                </label>
            </div>

            <label>
                <span>关系类型 *</span>
                <div class="rel-combo" data-combo="type">
                    <input type="text" class="rel-combo-input" placeholder="输入类型名搜索..." autocomplete="off">
                    <input type="hidden" name="type_id" value="<?= $preType > 0 ? $preType : '' ?>">
                    <div class="rel-combo-menu" hidden>
                        <?php foreach ($allTypes as $t): ?>
                            <div class="rel-combo-option"
                                 data-value="<?= (int)$t['id'] ?>"
                                 data-label="<?= e($t['name']) ?>"
                                 data-search="<?= e($t['category'] . ' ' . $t['name'] . ' ' . $t['reverse_name']) ?>"
                                 data-name="<?= e($t['name']) ?>"
                                 data-reverse="<?= e($t['reverse_name']) ?>"
                                 data-directed="<?= (int)$t['directed'] ?>">
                                <?= e($t['name']) ?> / <?= e($t['reverse_name']) ?>
                                <span class="rel-combo-hint"><?= e($t['category']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="rel-combo-empty" hidden>没有匹配的类型</div>
                    </div>
                </div>
            </label>

            <div class="rel-preview">
                <span class="rel-preview-label">预览</span>
                <span id="rel-preview-text">请先选择角色和关系类型</span>
            </div>

            <label>
                <span>备注（选填）</span>
                <textarea name="note" rows="3" maxlength="500"><?= e($preNote) ?></textarea>
            </label>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="admin.php?action=relations">返回列表</a>
            <button type="submit" class="btn-primary">保存</button>
        </div>
    </form>

</main>

<script>
(function () {
    // ===== Combobox =====
    function setupCombo(root) {
        var input = root.querySelector('.rel-combo-input');
        var hidden = root.querySelector('input[type="hidden"]');
        var menu = root.querySelector('.rel-combo-menu');
        var options = Array.prototype.slice.call(root.querySelectorAll('.rel-combo-option'));
        var empty = root.querySelector('.rel-combo-empty');

        // 回填
        if (hidden.value) {
            var m = options.find(function (o) { return o.dataset.value === hidden.value; });
            if (m) input.value = m.dataset.label;
        }

        function filter(kw) {
            kw = (kw || '').trim().toLowerCase();
            var visible = 0;
            options.forEach(function (o) {
                var hay = (o.dataset.search || o.dataset.label || '').toLowerCase();
                var show = kw === '' || hay.indexOf(kw) !== -1;
                o.hidden = !show;
                if (show) visible++;
            });
            if (empty) empty.hidden = visible !== 0;
            return visible;
        }

        function open() {
            filter(input.value === hidden.dataset.label ? '' : input.value);
            menu.hidden = false;
        }
        function close() {
            menu.hidden = true;
        }

        input.addEventListener('focus', open);
        input.addEventListener('input', function () {
            // 用户改文字 → 清空选择
            hidden.value = '';
            filter(input.value);
            menu.hidden = false;
            syncPreview();
        });
        input.addEventListener('blur', function () {
            setTimeout(close, 120);
        });

        options.forEach(function (o) {
            o.addEventListener('mousedown', function (e) {
                e.preventDefault(); // 防止 input blur 抢先
                input.value = o.dataset.label;
                hidden.value = o.dataset.value;
                close();
                syncPreview();
            });
        });

        // 键盘上下选择（可选，简单版）
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); return; }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            if (menu.hidden) { open(); return; }
            var list = options.filter(function (o) { return !o.hidden; });
            var idx = list.indexOf(document.activeElement);
            // 简版：这里不做焦点移动
        });
    }

    document.querySelectorAll('.rel-combo').forEach(setupCombo);

    // ===== 预览 =====
    function syncPreview() {
        var fromHidden = document.querySelector('.rel-combo[data-combo="from"] input[type="hidden"]');
        var toHidden   = document.querySelector('.rel-combo[data-combo="to"] input[type="hidden"]');
        var typeHidden = document.querySelector('.rel-combo[data-combo="type"] input[type="hidden"]');
        var fromInput  = document.querySelector('.rel-combo[data-combo="from"] .rel-combo-input');
        var toInput    = document.querySelector('.rel-combo[data-combo="to"] .rel-combo-input');
        var typeOpt    = document.querySelector('.rel-combo[data-combo="type"] .rel-combo-option[data-value="' + typeHidden.value + '"]');
        var preview    = document.getElementById('rel-preview-text');

        if (!fromHidden.value || !toHidden.value || !typeHidden.value) {
            preview.textContent = '请先选择角色和关系类型';
            return;
        }
        if (fromHidden.value === toHidden.value) {
            preview.textContent = '⚠️ 两个角色不能相同';
            return;
        }

        var fromText = fromInput.value;
        var toText = toInput.value;
        var typeName = typeOpt ? typeOpt.dataset.name : '';
        var reverseName = typeOpt ? typeOpt.dataset.reverse : '';
        var directed = typeOpt && typeOpt.dataset.directed === '1';

        if (directed) {
            preview.innerHTML =
                '<b>' + escapeHtml(fromText) + '</b> 是 <b>' + escapeHtml(toText) + '</b> 的 <b>' + escapeHtml(typeName) + '</b>' +
                '，<b>' + escapeHtml(toText) + '</b> 是 <b>' + escapeHtml(fromText) + '</b> 的 <b>' + escapeHtml(reverseName) + '</b>';
        } else {
            preview.innerHTML =
                '<b>' + escapeHtml(fromText) + '</b> 和 <b>' + escapeHtml(toText) + '</b> 是 <b>' + escapeHtml(typeName) + '</b>';
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // 初始预览
    syncPreview();
})();
</script>

<script src="../app.js"></script>
</body>
</html>