<?php
$pageTitle = '团队管理';
$teams = $pdo->query(
    'SELECT t.*, (SELECT COUNT(*) FROM rel_character_teams ct WHERE ct.team_id = t.id) AS member_count
     FROM rel_teams t ORDER BY t.sort, t.name'
)->fetchAll();
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
        <h2>新建团队</h2>
        <form method="post" action="admin.php?action=team_save" class="rel-inline-form">
            <?= csrf_field() ?>
            <input type="text" name="name" placeholder="团队名" maxlength="50" required>
            <input type="text" name="color" placeholder="#RRGGBB（选填）" maxlength="20">
            <button type="submit" class="btn-primary btn-sm">添加</button>
        </form>
    </section>

    <section class="settings-section" style="margin-top:20px;">
        <h2>全部团队（<?= count($teams) ?>）</h2>
        <?php if (!$teams): ?>
            <div class="settings-hint" style="margin-top:0;">还没有团队。</div>
        <?php else: ?>
            <div class="rel-team-list">
                <?php foreach ($teams as $t): ?>
                    <div class="rel-team-row">
                        <form method="post" action="admin.php?action=team_save" class="rel-inline-form" style="flex:1;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <span class="rel-team-dot" style="background: <?= e($t['color'] ?: '#888') ?>"></span>
                            <input type="text" name="name" value="<?= e($t['name']) ?>" maxlength="50" style="width:160px;">
                            <input type="text" name="color" value="<?= e($t['color']) ?>" maxlength="20" style="width:130px;">
                            <span class="rel-team-count"><?= (int)$t['member_count'] ?> 人</span>
                            <button type="submit" class="btn-secondary btn-sm">保存</button>
                        </form>
						<a class="btn-secondary btn-sm"
                           href="admin.php?action=team_members&id=<?= (int)$t['id'] ?>">成员</a>
                        <form method="post" action="admin.php?action=team_delete"
                              onsubmit="return confirm('删除该团队？角色本身不受影响。');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn-danger btn-sm">删除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<script src="../app.js"></script>
</body>
</html>