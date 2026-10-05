<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/relations/bootstrap.php';

require_rel_admin();

$action = (string)($_GET['action'] ?? 'dashboard');

function rel_handle_export(): void
{
    $data = rel_export_all();
    $filename = 'relations-' . date('Ymd-His') . '.json';

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ============================================================
 * 角色
 * ============================================================ */
if ($action === 'character_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        rel_delete_character($id);
        flash_set('角色已删除', 'success');
    }
    redirect('admin.php?action=characters');
}

if ($action === 'character_edit') {
    $id = (int)($_GET['id'] ?? 0);
    $char = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rel_characters WHERE id = ?');
        $stmt->execute([$id]);
        $char = $stmt->fetch();
        if (!$char) { flash_set('角色不存在', 'error'); redirect('admin.php?action=characters'); }
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $name   = trim((string)($_POST['name'] ?? ''));
        $color  = trim((string)($_POST['color'] ?? ''));
        $avatar = trim((string)($_POST['avatar_url'] ?? ''));
        $wiki   = trim((string)($_POST['wiki_url'] ?? ''));
        $note   = trim((string)($_POST['note'] ?? ''));
        $teams  = $_POST['teams'] ?? [];

        if ($name === '' || mb_strlen($name) > 50) $errors[] = '名字必填，不超过 50 字';
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $errors[] = '颜色格式应为 #RRGGBB';
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) $errors[] = '头像 URL 无效';
        if ($wiki !== '' && !filter_var($wiki, FILTER_VALIDATE_URL)) $errors[] = 'Wiki URL 无效';
        if (mb_strlen($note) > 500) $errors[] = '简介不超过 500 字';

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE rel_characters SET name=?, color=?, avatar_url=?, wiki_url=?, note=? WHERE id=?'
                    );
                    $stmt->execute([
                        $name, $color, $avatar !== '' ? $avatar : null,
                        $wiki !== '' ? $wiki : null, $note !== '' ? $note : null, $id
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO rel_characters (name, color, avatar_url, wiki_url, note, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $name, $color, $avatar !== '' ? $avatar : null,
                        $wiki !== '' ? $wiki : null, $note !== '' ? $note : null,
                        date('Y-m-d H:i:s')
                    ]);
                    $id = (int)$pdo->lastInsertId();
                }

                // 更新团队关联
                $pdo->prepare('DELETE FROM rel_character_teams WHERE character_id = ?')->execute([$id]);
                if (is_array($teams) && $teams) {
                    $ins = $pdo->prepare('INSERT OR IGNORE INTO rel_character_teams (character_id, team_id) VALUES (?, ?)');
                    foreach ($teams as $tid) {
                        $tid = (int)$tid;
                        if ($tid > 0) $ins->execute([$id, $tid]);
                    }
                }

                $pdo->commit();
                flash_set($id > 0 ? '已保存' : '已创建', 'success');
                redirect('admin.php?action=character_edit&id=' . $id);
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = '保存失败：' . $e->getMessage();
            }
        }
    }

    require __DIR__ . '/../../includes/relations/views/admin_character_edit.php';
    exit;
}

/* ============================================================
 * 团队
 * ============================================================ */
if ($action === 'team_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim((string)($_POST['name'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));

    if ($name === '') {
        flash_set('团队名不能为空', 'error');
    } elseif ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        flash_set('颜色格式不对', 'error');
    } else {
        if ($id > 0) {
            $pdo->prepare('UPDATE rel_teams SET name=?, color=? WHERE id=?')->execute([$name, $color, $id]);
        } else {
            $pdo->prepare('INSERT INTO rel_teams (name, color) VALUES (?, ?)')->execute([$name, $color]);
        }
        flash_set('已保存', 'success');
    }
    redirect('admin.php?action=teams');
}

if ($action === 'team_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM rel_character_teams WHERE team_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM rel_teams WHERE id = ?')->execute([$id]);
        flash_set('团队已删除', 'success');
    }
    redirect('admin.php?action=teams');
}

/* ============================================================
 * 关系类型
 * ============================================================ */
if ($action === 'type_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $reverse = trim((string)($_POST['reverse_name'] ?? ''));
    $cat     = trim((string)($_POST['category'] ?? '其他'));
    $directed = !empty($_POST['directed']) ? 1 : 0;

    if ($name === '' || $reverse === '') {
        flash_set('正向和反向名称都要填', 'error');
    } elseif ($cat === '') {
        flash_set('分类不能为空', 'error');
    } else {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE rel_relation_types SET name=?, reverse_name=?, category=?, directed=? WHERE id=?'
            )->execute([$name, $reverse, $cat, $directed, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO rel_relation_types (name, reverse_name, category, directed) VALUES (?, ?, ?, ?)'
            )->execute([$name, $reverse, $cat, $directed]);
        }
        flash_set('已保存', 'success');
    }
    redirect('admin.php?action=types');
}

if ($action === 'type_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $used = rel_type_is_used($id);
    if ($used > 0) {
        flash_set("还有 $used 条关系在用这个类型，无法删除", 'error');
    } else {
        $pdo->prepare('DELETE FROM rel_relation_types WHERE id = ?')->execute([$id]);
        flash_set('类型已删除', 'success');
    }
    redirect('admin.php?action=types');
}

/* ============================================================
 * 关系
 * ============================================================ */
if ($action === 'relation_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM rel_relations WHERE id = ?')->execute([$id]);
        flash_set('关系已删除', 'success');
    }
    redirect('admin.php?action=relations');
}

if ($action === 'relation_edit') {
    $id = (int)($_GET['id'] ?? 0);
    $rel = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rel_relations WHERE id = ?');
        $stmt->execute([$id]);
        $rel = $stmt->fetch();
        if (!$rel) { flash_set('关系不存在', 'error'); redirect('admin.php?action=relations'); }
    }
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $fromId = (int)($_POST['from_id'] ?? 0);
        $toId   = (int)($_POST['to_id'] ?? 0);
        $typeId = (int)($_POST['type_id'] ?? 0);
        $note   = trim((string)($_POST['note'] ?? ''));

        if ($fromId <= 0 || $toId <= 0) $errors[] = '必须选择两个角色';
        if ($fromId === $toId) $errors[] = '两个角色不能相同';
        if ($typeId <= 0) $errors[] = '必须选择关系类型';
        if (mb_strlen($note) > 500) $errors[] = '备注不超过 500 字';

        if (!$errors) {
            if ($id > 0) {
                $pdo->prepare('UPDATE rel_relations SET from_id=?, to_id=?, type_id=?, note=? WHERE id=?')
                    ->execute([$fromId, $toId, $typeId, $note !== '' ? $note : null, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO rel_relations (from_id, to_id, type_id, note, created_at) VALUES (?, ?, ?, ?, ?)'
                )->execute([$fromId, $toId, $typeId, $note !== '' ? $note : null, date('Y-m-d H:i:s')]);
                $id = (int)$pdo->lastInsertId();
            }
            flash_set('已保存', 'success');
            redirect('admin.php?action=relation_edit&id=' . $id);
        }
    }

    require __DIR__ . '/../../includes/relations/views/admin_relation_edit.php';
    exit;
}

/* ---------- 团队：成员管理 ---------- */
if ($action === 'team_members') {
    $teamId = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM rel_teams WHERE id = ?');
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();
    if (!$team) {
        flash_set('团队不存在', 'error');
        redirect('admin.php?action=teams');
    }

    $allChars = rel_all_characters();

    // 当前团队成员 id 集合
    $stmt = $pdo->prepare('SELECT character_id FROM rel_character_teams WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $memberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    require __DIR__ . '/../../includes/relations/views/admin_team_members.php';
    exit;
}

if ($action === 'team_members_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $teamId = (int)($_POST['team_id'] ?? 0);
    $ids    = $_POST['character_ids'] ?? [];

    if ($teamId > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM rel_character_teams WHERE team_id = ?')->execute([$teamId]);
            if (is_array($ids) && $ids) {
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO rel_character_teams (character_id, team_id) VALUES (?, ?)');
                foreach ($ids as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $stmt->execute([$cid, $teamId]);
                }
            }
            $pdo->commit();
            flash_set('成员已更新', 'success');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('保存失败：' . $e->getMessage(), 'error');
        }
    }
    redirect('admin.php?action=team_members&id=' . $teamId);
}

/* ---------- 快速添加关系（从角色页发起） ---------- */
if ($action === 'quick_relation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $fromId = (int)($_POST['from_id'] ?? 0);
    $toId   = (int)($_POST['to_id'] ?? 0);
    $typeId = (int)($_POST['type_id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));
    $backId = (int)($_POST['back_id'] ?? 0);

    $errors = [];
    if ($fromId <= 0 || $toId <= 0) $errors[] = '必须选择两个角色';
    if ($fromId === $toId) $errors[] = '两个角色不能相同';
    if ($typeId <= 0) $errors[] = '必须选择关系类型';
    if (mb_strlen($note) > 500) $errors[] = '备注不超过 500 字';

    if ($errors) {
        flash_set(implode('；', $errors), 'error');
    } else {
        // 查重：同样的 from/to/type 已存在就更新备注
        $stmt = $pdo->prepare(
            'SELECT id FROM rel_relations WHERE from_id = ? AND to_id = ? AND type_id = ? LIMIT 1'
        );
        $stmt->execute([$fromId, $toId, $typeId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $pdo->prepare('UPDATE rel_relations SET note = ? WHERE id = ?')
                ->execute([$note !== '' ? $note : null, $existing]);
            flash_set('关系已存在，备注已更新', 'info');
        } else {
            $pdo->prepare(
                'INSERT INTO rel_relations (from_id, to_id, type_id, note, created_at)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $fromId, $toId, $typeId,
                $note !== '' ? $note : null,
                date('Y-m-d H:i:s'),
            ]);
            flash_set('关系已添加', 'success');
        }
    }

    redirect('admin.php?action=character_edit&id=' . $backId);
}

/* ---------- 重新生成 token ---------- */
if ($action === 'regenerate_token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    rel_regenerate_api_token();
    flash_set('Token 已重新生成', 'success');
    redirect('admin.php');
}

/* ---------- 保存前台标题 ---------- */
if ($action === 'save_title' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newTitle = trim((string)($_POST['page_title'] ?? ''));
    if (mb_strlen($newTitle) > 60) {
        flash_set('标题不能超过 60 字', 'error');
    } else {
        set_setting('rel_page_title', $newTitle);
        flash_set('已保存', 'success');
    }
    redirect('admin.php');
}

/* ============================================================
 * 路由
 * ============================================================ */
switch ($action) {
    case 'characters':     require __DIR__ . '/../../includes/relations/views/admin_characters.php'; break;
    case 'teams':          require __DIR__ . '/../../includes/relations/views/admin_teams.php'; break;
    case 'types':          require __DIR__ . '/../../includes/relations/views/admin_types.php'; break;
    case 'relations':      require __DIR__ . '/../../includes/relations/views/admin_relations.php'; break;
	case 'import':         require __DIR__ . '/../../includes/relations/views/admin_import.php'; break;
    case 'export':         rel_handle_export(); break;
    default:               require __DIR__ . '/../../includes/relations/views/admin_dashboard.php'; break;
}