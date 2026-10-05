<?php
$pageTitle = $id > 0 ? '编辑角色' : '新建角色';
$allTeams = rel_all_teams();
$charTeamIds = $id > 0 ? array_map(fn($t) => (int)$t['id'], rel_teams_of($id)) : [];
$myRelations = $id > 0 ? rel_relations_of($id) : [];
$allChars = rel_all_characters();
$allTypes = rel_types_grouped();
$flash = flash_get();
$allTypesFlat = rel_all_types();
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

    <form method="post" class="settings-form">
        <?= csrf_field() ?>

        <section class="settings-section">
            <h2>基本信息</h2>
            <label>
                <span>名字 *</span>
                <input type="text" name="name" maxlength="50" required
                       value="<?= e($_POST['name'] ?? $char['name'] ?? '') ?>">
            </label>
            <label>
                <span>代表色（选填，留空自动分配）</span>
                <input type="text" name="color" maxlength="20" placeholder="#ff7aab"
                       value="<?= e($_POST['color'] ?? $char['color'] ?? '') ?>">
            </label>
            <label>
                <span>头像 URL（选填）</span>
                <input type="url" name="avatar_url" maxlength="500"
                       value="<?= e($_POST['avatar_url'] ?? $char['avatar_url'] ?? '') ?>">
            </label>
            <label>
                <span>Wiki URL（选填）</span>
                <input type="url" name="wiki_url" maxlength="500"
                       value="<?= e($_POST['wiki_url'] ?? $char['wiki_url'] ?? '') ?>">
            </label>
            <label>
                <span>简介（选填）</span>
                <textarea name="note" rows="3" maxlength="500"><?= e($_POST['note'] ?? $char['note'] ?? '') ?></textarea>
            </label>
        </section>

        <section class="settings-section">
            <h2>所属团队</h2>
            <?php if (!$allTeams): ?>
                <div class="settings-hint" style="margin-top:0;">
                    还没有团队，去 <a href="admin.php?action=teams">团队管理</a> 添加。
                </div>
            <?php else: ?>
                <div class="rel-team-picker">
                    <?php foreach ($allTeams as $t): ?>
                        <?php
                            $tid = (int)$t['id'];
                            $tcolor = $t['color'] ?: '';
                            $checked = in_array($tid, $charTeamIds, true);
                        ?>
                        <label class="rel-team-chip"
                               <?= $tcolor === '' ? 'data-no-color' : '' ?>
                               style="<?= $tcolor !== '' ? '--c: ' . e($tcolor) . ';' : '' ?>">
                            <input type="checkbox" name="teams[]" value="<?= $tid ?>" <?= $checked ? 'checked' : '' ?>>
                            <span class="rel-team-chip-dot"></span>
                            <span class="rel-team-chip-name"><?= e($t['name']) ?></span>
                            <span class="rel-team-chip-check">✓</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="settings-hint" style="margin-top:8px;">
                    点一下选中，再点取消。一个角色可以属于多个团队。
                </div>
            <?php endif; ?>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="admin.php?action=characters">返回列表</a>
            <button type="submit" class="btn-primary">保存</button>
        </div>
    </form>

    <?php if ($id > 0): ?>
    <section class="settings-section" style="margin-top:20px;">
        <h2>
            关系
            <a href="admin.php?action=relation_edit&from_id=<?= $id ?>"
               style="float:right; font-size:13px; font-weight:400;">+ 新建关系</a>
        </h2>

        <?php if (!$myRelations): ?>
            <div class="settings-hint" style="margin-top:0;">该角色还没有任何关系。</div>
        <?php else: ?>
            <div class="rel-rel-list">
                <?php foreach ($myRelations as $r): ?>
                    <?php $v = rel_label_for_viewer($r, $id); ?>
                    <div class="rel-rel-row">
                        <span class="rel-rel-dir"><?= $v['direction'] === 'out' ? '→' : '←' ?></span>
                        <span class="rel-rel-type"><?= e($v['label']) ?></span>
                        <a class="rel-rel-other" href="admin.php?action=character_edit&id=<?= $v['other_id'] ?>">
                            <?= e($v['other']) ?>
                        </a>
                        <?php if (!empty($r['note'])): ?>
                            <span class="rel-rel-note">（<?= e($r['note']) ?>）</span>
                        <?php endif; ?>
                        <a class="btn-secondary btn-sm" style="margin-left:auto;"
                           href="admin.php?action=relation_edit&id=<?= (int)$r['id'] ?>">编辑</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
	
	<!-- 快速添加关系 -->
        <details class="qr-add">
            <summary>+ 快速添加关系</summary>
            <form method="post" action="admin.php?action=quick_relation" class="qr-form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="back_id" value="<?= (int)$id ?>">
                <input type="hidden" name="from_id" id="qr-from-id" value="<?= (int)$id ?>">
                <input type="hidden" name="to_id"   id="qr-to-id" value="">

                <div class="qr-row">
                    <span class="qr-self"><?= e($char['name']) ?></span>

                    <select name="type_id" id="qr-type" required>
                        <option value="">选择关系类型</option>
                        <?php foreach ($allTypesFlat as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                    data-name="<?= e($t['name']) ?>"
                                    data-reverse="<?= e($t['reverse_name']) ?>"
                                    data-directed="<?= (int)$t['directed'] ?>">
                                <?= e($t['name']) ?> / <?= e($t['reverse_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="qr-direction">
                        <option value="out">指向 →</option>
                        <option value="in">← 指向我</option>
                    </select>

                    <div class="rel-combo" data-combo="qr-to">
                        <input type="text" class="rel-combo-input" placeholder="输入名字搜索对方…" autocomplete="off">
                        <div class="rel-combo-menu" hidden>
                            <?php foreach ($allChars as $oc): ?>
                                <?php if ((int)$oc['id'] === $id) continue; ?>
                                <div class="rel-combo-option"
                                     data-value="<?= (int)$oc['id'] ?>"
                                     data-label="<?= e($oc['name']) ?>"><?= e($oc['name']) ?></div>
                            <?php endforeach; ?>
                            <div class="rel-combo-empty" hidden>没有匹配的角色</div>
                        </div>
                    </div>
                </div>

                <div class="qr-preview" id="qr-preview">
                    <span class="rel-preview-label">预览</span>
                    <span id="qr-preview-text">请选择类型和对方角色</span>
                </div>

                <label class="qr-note">
                    <span>备注（选填）</span>
                    <input type="text" name="note" maxlength="500" placeholder="例如：初次见面时打过一架">
                </label>

                <div class="form-actions" style="margin-top:8px;">
                    <button type="submit" class="btn-primary btn-sm">添加</button>
                </div>
            </form>
        </details>
    </section>
    <?php endif; ?>

</main>

<script>
(function () {
    var root = document.querySelector('.qr-add');
    if (!root) return;

    var fromHidden = document.getElementById('qr-from-id');
    var toHidden   = document.getElementById('qr-to-id');
    var typeSel    = document.getElementById('qr-type');
    var dirSel     = document.getElementById('qr-direction');
    var preview    = document.getElementById('qr-preview-text');
    var selfName   = root.querySelector('.qr-self').textContent;

    // 复用可搜索下拉：需要把 rel-combo 相关的初始化复制一遍
    var comboRoot = root.querySelector('.rel-combo');
    var comboInput = comboRoot.querySelector('.rel-combo-input');
    var comboMenu = comboRoot.querySelector('.rel-combo-menu');
    var options = Array.prototype.slice.call(comboRoot.querySelectorAll('.rel-combo-option'));
    var empty = comboRoot.querySelector('.rel-combo-empty');

    function filterOptions(kw) {
        kw = (kw || '').trim().toLowerCase();
        var visible = 0;
        options.forEach(function (o) {
            var hay = (o.dataset.label || '').toLowerCase();
            var show = kw === '' || hay.indexOf(kw) !== -1;
            o.hidden = !show;
            if (show) visible++;
        });
        if (empty) empty.hidden = visible !== 0;
    }

    comboInput.addEventListener('focus', function () {
        filterOptions('');
        comboMenu.hidden = false;
    });
    comboInput.addEventListener('input', function () {
        toHidden.value = '';
        filterOptions(comboInput.value);
        comboMenu.hidden = false;
        syncPreview();
    });
    comboInput.addEventListener('blur', function () {
        setTimeout(function () { comboMenu.hidden = true; }, 120);
    });
    options.forEach(function (o) {
        o.addEventListener('mousedown', function (e) {
            e.preventDefault();
            comboInput.value = o.dataset.label;
            toHidden.value = o.dataset.value;
            comboMenu.hidden = true;
            syncPreview();
        });
    });

    function syncPreview() {
        var typeOpt = typeSel.options[typeSel.selectedIndex];
        if (!typeOpt || !typeSel.value || !toHidden.value) {
            preview.textContent = '请选择类型和对方角色';
            return;
        }

        var fromName = fromHidden.value;
        var toName   = toHidden.value;
        var typeName = typeOpt.dataset.name || '';
        var reverse  = typeOpt.dataset.reverse || '';
        var directed = typeOpt.dataset.directed === '1';
        var dir      = dirSel.value;

        // 根据方向组装最终 from/to
        var finalFrom, finalTo, label;
        if (dir === 'in') {
            finalFrom = toHidden.value;
            finalTo   = fromHidden.value;
            label = directed ? (selfName + ' 是 ' + comboInput.value + ' 的 ' + reverse)
                             : (selfName + ' 和 ' + comboInput.value + ' 是 ' + typeName);
        } else {
            finalFrom = fromHidden.value;
            finalTo   = toHidden.value;
            label = directed ? (selfName + ' 是 ' + comboInput.value + ' 的 ' + typeName)
                             : (selfName + ' 和 ' + comboInput.value + ' 是 ' + typeName);
        }

        // 真正提交时改值
        fromHidden.value = finalFrom;
        toHidden.value   = finalTo;

        preview.innerHTML = '<b>' + escapeHtml(label) + '</b>';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    typeSel.addEventListener('change', syncPreview);
    dirSel.addEventListener('change', syncPreview);
})();
</script>

<script src="../app.js"></script>
</body>
</html>