<?php
$pageTitle = '关系类型';
$grouped = rel_types_grouped();
$flash = flash_get();
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
        <h2>新建关系类型</h2>
        <form method="post" action="admin.php?action=type_save" class="rel-inline-form">
            <?= csrf_field() ?>
            <input type="text" name="name" placeholder="正向名（如 师父）" maxlength="30" required>
            <input type="text" name="reverse_name" placeholder="反向名（如 徒弟）" maxlength="30" required>
            <input type="text" name="category" placeholder="分类（如 职业）" maxlength="30" value="其他">
            <label class="rel-checkbox" style="margin:0;">
                <input type="checkbox" name="directed" value="1" checked>
                <span>有向</span>
            </label>
            <button type="submit" class="btn-primary btn-sm">添加</button>
        </form>
        <div class="settings-hint">
            「有向」= A 和 B 有区别（A 是 B 的师父，B 是 A 的徒弟）；
            无向 = A 和 B 对称（朋友、同事）。
        </div>
    </section>

    <?php foreach ($grouped as $cat => $types): ?>
        <?php $usedTotal = 0; foreach ($types as $t) $usedTotal += rel_type_is_used((int)$t['id']); ?>
        <section class="settings-section" style="margin-top:16px;">
            <details>
                <summary>
                    <span><?= e($cat) ?></span>
                    <span class="rel-cat-count"><?= count($types) ?> 种 · <?= $usedTotal ?> 条关系</span>
                </summary>
                <div class="rel-type-list">
                    <?php foreach ($types as $t): ?>
                        <?php $used = rel_type_is_used((int)$t['id']); ?>
                        <div class="rel-type-row">
                            <form method="post" action="admin.php?action=type_save" class="rel-inline-form" style="flex:1;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <input type="text" name="name" value="<?= e($t['name']) ?>" maxlength="30" style="width:130px;">
                                <span class="rel-arrow">↔</span>
                                <input type="text" name="reverse_name" value="<?= e($t['reverse_name']) ?>" maxlength="30" style="width:130px;">
                                <input type="text" name="category" value="<?= e($t['category']) ?>" maxlength="30" style="width:100px;">
                                <label class="rel-checkbox" style="margin:0;">
                                    <input type="checkbox" name="directed" value="1" <?= $t['directed'] ? 'checked' : '' ?>>
                                    <span>有向</span>
                                </label>
                                <span class="rel-team-count"><?= $used ?> 条</span>
                                <button type="submit" class="btn-secondary btn-sm">保存</button>
                            </form>
                            <form method="post" action="admin.php?action=type_delete"
                                  onsubmit="return confirm('删除该类型？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn-danger btn-sm" <?= $used > 0 ? 'disabled title="有引用，不能删"' : '' ?>>删除</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </section>
    <?php endforeach; ?>

</main>

<script src="../app.js"></script>
</body>
</html>