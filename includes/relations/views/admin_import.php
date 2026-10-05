<?php
$pageTitle = '批量导入 / 导出';
$flash = flash_get();
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $json = (string)($_POST['json'] ?? '');
    $mode = ($_POST['mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';

    if ($mode === 'replace' && ($_POST['confirm_replace'] ?? '') !== 'yes') {
        $importResult = ['error' => '替换模式需要勾选确认'];
    } else {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $importResult = ['error' => 'JSON 解析失败：' . json_last_error_msg()];
        } else {
            try {
                $stats = rel_import_data($data, $mode);
                $importResult = ['success' => $stats, 'mode' => $mode];
                flash_set('导入完成', 'success');
            } catch (Throwable $e) {
                $importResult = ['error' => '导入失败：' . $e->getMessage()];
            }
        }
    }
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

    <section class="settings-section">
        <h2>导出</h2>
        <div class="settings-hint" style="margin-top:0;">
            下载当前所有数据为 JSON，可用于备份或迁移到另一套关系图。
        </div>
        <div class="form-actions" style="justify-content:flex-start;">
            <a class="btn-primary" href="admin.php?action=export" style="text-decoration:none;">💾 下载 JSON</a>
        </div>
    </section>

    <section class="settings-section" style="margin-top:20px;">
        <h2>导入</h2>

        <?php if ($importResult): ?>
            <?php if (!empty($importResult['error'])): ?>
                <div class="alert alert-error"><?= e($importResult['error']) ?></div>
            <?php else: ?>
                <?php $s = $importResult['success']; ?>
                <div class="alert alert-success">
                    <div><b>导入完成</b>（模式：<?= $importResult['mode'] === 'replace' ? '替换' : '合并' ?>）</div>
                    <div style="margin-top:6px; font-size:13px; line-height:1.7;">
                        团队：新建 <?= $s['teams_created'] ?>，更新 <?= $s['teams_updated'] ?><br>
                        角色：新建 <?= $s['characters_created'] ?>，更新 <?= $s['characters_updated'] ?><br>
                        类型：新建 <?= $s['types_created'] ?>，更新 <?= $s['types_updated'] ?><br>
                        关系：新建 <?= $s['relations_created'] ?>，更新 <?= $s['relations_updated'] ?>，跳过 <?= $s['relations_skipped'] ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($importResult['success']['errors'])): ?>
                <div class="alert alert-error" style="margin-top:12px;">
                    <div><b>警告：<?= count($importResult['success']['errors']) ?> 条问题</b></div>
                    <div style="margin-top:6px; font-size:12px; line-height:1.7; max-height:200px; overflow-y:auto;">
                        <?php foreach (array_slice($importResult['success']['errors'], 0, 30) as $err): ?>
                            <div>· <?= e($err) ?></div>
                        <?php endforeach; ?>
                        <?php if (count($importResult['success']['errors']) > 30): ?>
                            <div>· …还有 <?= count($importResult['success']['errors']) - 30 ?> 条</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="settings-hint" style="margin-top:0;">
                JSON 格式示例：
                <pre class="rel-code">{
  "teams": [
    {"name": "主角组", "color": "#ff7aab"},
    {"name": "反派", "color": "#7d5cff"}
  ],
  "characters": [
    {"name": "小狐狸", "color": "#ff7aab", "avatar": "https://...", "wiki": "https://...", "note": "简介", "teams": ["主角组"]},
    {"name": "白兔", "teams": ["主角组", "反派"]}
  ],
  "types": [
    {"name": "师父", "reverse": "徒弟", "category": "职业", "directed": 1}
  ],
  "relations": [
    {"from": "小狐狸", "to": "白兔", "type": "师父", "note": "备注"}
  ]
}</pre>
            </div>

            <div class="rel-mode-picker">
                <label class="rel-mode">
                    <input type="radio" name="mode" value="merge" checked>
                    <div>
                        <b>合并</b>
                        <div class="rel-mode-hint">按名称匹配，存在则更新，不存在则新建。适合追加/增量导入。</div>
                    </div>
                </label>
                <label class="rel-mode">
                    <input type="radio" name="mode" value="replace" id="mode-replace">
                    <div>
                        <b>替换</b>
                        <div class="rel-mode-hint">⚠️ 先清空所有角色、团队、类型、关系，再导入。不可恢复。</div>
                    </div>
                </label>
            </div>

            <label class="rel-checkbox" id="confirm-replace" style="display:none; margin: 10px 0;">
                <input type="checkbox" name="confirm_replace" value="yes">
                <span>我确认要清空现有数据</span>
            </label>

            <label>
                <span>JSON 数据</span>
                <textarea name="json" rows="16" required
                          placeholder="粘贴 JSON..." class="rel-code-input"><?= e($_POST['json'] ?? '') ?></textarea>
            </label>

            <div class="form-actions">
                <a class="btn-secondary" href="admin.php">返回</a>
                <button type="submit" class="btn-primary">导入</button>
            </div>
        </form>
    </section>

</main>

<script>
(function () {
    var replaceRadio = document.getElementById('mode-replace');
    var confirmBox = document.getElementById('confirm-replace');
    if (!replaceRadio || !confirmBox) return;

    document.querySelectorAll('input[name="mode"]').forEach(function (r) {
        r.addEventListener('change', function () {
            confirmBox.style.display = replaceRadio.checked ? 'flex' : 'none';
        });
    });
})();
</script>

<script src="../app.js"></script>
</body>
</html>