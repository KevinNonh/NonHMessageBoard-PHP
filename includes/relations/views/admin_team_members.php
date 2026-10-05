<?php
$pageTitle = '团队成员 · ' . $team['name'];
$flash = flash_get();
$memberSet = array_flip($memberIds);
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

    <div class="tmm-head">
        <div>
            <span class="tmm-team-dot" style="background: <?= e($team['color'] ?: '#888') ?>"></span>
            <strong><?= e($team['name']) ?></strong>
        </div>
        <div class="tmm-count">
            已选 <span id="tmm-selected-count"><?= count($memberIds) ?></span> 人
        </div>
    </div>

    <div class="tmm-toolbar">
        <input type="search" id="tmm-search" placeholder="搜索角色名…" autocomplete="off">
        <button type="button" class="btn-secondary btn-sm" id="tmm-select-all">全选当前</button>
        <button type="button" class="btn-secondary btn-sm" id="tmm-clear">清空全部</button>
    </div>

    <form method="post" action="admin.php?action=team_members_save" id="tmm-form">
        <?= csrf_field() ?>
        <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">

        <div class="tmm-grid" id="tmm-grid">
            <?php foreach ($allChars as $c): ?>
                <?php $checked = isset($memberSet[(int)$c['id']]); ?>
                <label class="tmm-chip<?= $checked ? ' checked' : '' ?>"
                       data-name="<?= e(mb_strtolower($c['name'])) ?>"
                       style="--c: <?= e($c['color'] ?: rel_default_color((int)$c['id'])) ?>;">
                    <input type="checkbox" name="character_ids[]" value="<?= (int)$c['id'] ?>"
                        <?= $checked ? 'checked' : '' ?>>
                    <span class="tmm-chip-avatar"
                          <?= empty($c['avatar_url']) ? 'data-initial="' . e(mb_substr($c['name'], 0, 1)) . '"' : '' ?>>
                        <?php if (!empty($c['avatar_url'])): ?>
                            <img src="<?= e($c['avatar_url']) ?>" alt="" referrerpolicy="no-referrer" loading="lazy">
                        <?php endif; ?>
                    </span>
                    <span class="tmm-chip-name"><?= e($c['name']) ?></span>
                    <span class="tmm-chip-check">✓</span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="tmm-footer">
            <a class="btn-secondary" href="admin.php?action=teams">返回团队列表</a>
            <button type="submit" class="btn-primary">保存</button>
        </div>
    </form>

</main>

<script>
(function () {
    var grid = document.getElementById('tmm-grid');
    if (!grid) return;

    var chips = Array.prototype.slice.call(grid.querySelectorAll('.tmm-chip'));
    var searchInput = document.getElementById('tmm-search');
    var countEl = document.getElementById('tmm-selected-count');

    function updateCount() {
        var n = 0;
        chips.forEach(function (chip) {
            if (chip.querySelector('input').checked) n++;
        });
        if (countEl) countEl.textContent = n;
    }

    function updateChipState(chip) {
        var cb = chip.querySelector('input');
        chip.classList.toggle('checked', cb.checked);
    }

    chips.forEach(function (chip) {
        var cb = chip.querySelector('input');
        cb.addEventListener('change', function () {
            updateChipState(chip);
            updateCount();
        });
        // 点击 chip 任何位置都触发切换（label 天然支持，但为了视觉一致保留）
    });

    // 搜索
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var kw = searchInput.value.trim().toLowerCase();
            chips.forEach(function (chip) {
                var name = chip.dataset.name || '';
                chip.style.display = (kw === '' || name.indexOf(kw) !== -1) ? '' : 'none';
            });
        });
    }

    // 全选当前可见
    var selectAllBtn = document.getElementById('tmm-select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            chips.forEach(function (chip) {
                if (chip.style.display === 'none') return;
                var cb = chip.querySelector('input');
                if (!cb.checked) {
                    cb.checked = true;
                    updateChipState(chip);
                }
            });
            updateCount();
        });
    }

    // 清空全部
    var clearBtn = document.getElementById('tmm-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!confirm('清空该团队所有成员？')) return;
            chips.forEach(function (chip) {
                var cb = chip.querySelector('input');
                if (cb.checked) {
                    cb.checked = false;
                    updateChipState(chip);
                }
            });
            updateCount();
        });
    }

    updateCount();
})();
</script>

<script src="../app.js"></script>
</body>
</html>