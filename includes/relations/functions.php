<?php
declare(strict_types=1);

function rel_all_characters(): array
{
    global $pdo;
    return $pdo->query('SELECT * FROM rel_characters ORDER BY sort, name')->fetchAll();
}

function rel_all_teams(): array
{
    global $pdo;
    return $pdo->query('SELECT * FROM rel_teams ORDER BY sort, name')->fetchAll();
}

function rel_all_types(): array
{
    global $pdo;
    return $pdo->query('SELECT * FROM rel_relation_types ORDER BY category, sort, name')->fetchAll();
}

/** 按分类分组的关系类型 */
function rel_types_grouped(): array
{
    $rows = rel_all_types();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['category']][] = $r;
    }
    return $out;
}

/** 一个角色的所有关系（双向视角） */
function rel_relations_of(int $characterId): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT r.*,
                t.name AS type_name, t.reverse_name AS type_reverse, t.directed,
                cf.name AS from_name, cf.color AS from_color,
                ct.name AS to_name,   ct.color AS to_color
         FROM rel_relations r
         JOIN rel_relation_types t ON t.id = r.type_id
         JOIN rel_characters cf    ON cf.id = r.from_id
         JOIN rel_characters ct    ON ct.id = r.to_id
         WHERE r.from_id = ? OR r.to_id = ?
         ORDER BY r.id DESC'
    );
    $stmt->execute([$characterId, $characterId]);
    return $stmt->fetchAll();
}

/** 计算某条关系从"当前角色"视角看应该显示什么标签 */
function rel_label_for_viewer(array $relation, int $viewerId): array
{
    $isFrom = ((int)$relation['from_id'] === $viewerId);
    return [
        'label'    => $isFrom ? $relation['type_name'] : $relation['type_reverse'],
        'other_id' => $isFrom ? (int)$relation['to_id'] : (int)$relation['from_id'],
        'other'    => $isFrom ? $relation['to_name'] : $relation['from_name'],
        'direction'=> $isFrom ? 'out' : 'in',
    ];
}

/** 一个角色所属的团队 */
function rel_teams_of(int $characterId): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT t.* FROM rel_teams t
         JOIN rel_character_teams ct ON ct.team_id = t.id
         WHERE ct.character_id = ?
         ORDER BY t.sort, t.name'
    );
    $stmt->execute([$characterId]);
    return $stmt->fetchAll();
}

/** 删除角色时级联清理关系与团队关联 */
function rel_delete_character(int $id): void
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM rel_relations WHERE from_id = ? OR to_id = ?')->execute([$id, $id]);
        $pdo->prepare('DELETE FROM rel_character_teams WHERE character_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM rel_characters WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** 关系类型是否被引用 */
function rel_type_is_used(int $typeId): int
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rel_relations WHERE type_id = ?');
    $stmt->execute([$typeId]);
    return (int)$stmt->fetchColumn();
}

/** 两人之间所有关系的合并标签（用于图上一条边） */
function rel_merged_label(array $relations, int $fromId, int $toId): string
{
    $names = [];
    foreach ($relations as $r) {
        if ((int)$r['from_id'] === $fromId && (int)$r['to_id'] === $toId) {
            $names[] = $r['type_name'];
        } elseif ((int)$r['from_id'] === $toId && (int)$r['to_id'] === $fromId) {
            $names[] = $r['type_reverse'];
        }
    }
    return implode(' / ', array_unique($names));
}

/** 生成角色头像展示用的首字 */
function rel_initial(string $name): string
{
    return mb_substr($name, 0, 1);
}

/** 从一组颜色里取一个默认色（按 id 哈希） */
function rel_default_color(int $id): string
{
    $palette = ['#ff7aab', '#6aa9ff', '#7bd48d', '#ffb066', '#b388ff', '#66d9d9', '#ff8a80', '#c9a86a'];
    return $palette[$id % count($palette)];
}
/**
 * 导出全部数据为数组（用于 JSON 序列化）
 */
function rel_export_all(): array
{
    global $pdo;

    $teams = [];
    foreach ($pdo->query('SELECT id, name, color FROM rel_teams ORDER BY sort, id') as $t) {
        $teams[(int)$t['id']] = [
            'name'  => $t['name'],
            'color' => $t['color'] ?: '',
        ];
    }

    $characters = [];
    $charTeams = [];
    foreach ($pdo->query('SELECT character_id, team_id FROM rel_character_teams') as $r) {
        $charTeams[(int)$r['character_id']][] = (int)$r['team_id'];
    }
    foreach ($pdo->query('SELECT * FROM rel_characters ORDER BY sort, id') as $c) {
        $cid = (int)$c['id'];
        $teamNames = [];
        foreach ($charTeams[$cid] ?? [] as $tid) {
            if (isset($teams[$tid])) $teamNames[] = $teams[$tid]['name'];
        }
        $characters[] = [
            'name'   => $c['name'],
            'color'  => $c['color'] ?: '',
            'avatar' => $c['avatar_url'] ?: '',
            'wiki'   => $c['wiki_url'] ?: '',
            'note'   => $c['note'] ?: '',
            'teams'  => $teamNames,
            'x'      => $c['pos_x'] !== null ? (float)$c['pos_x'] : null,
            'y'      => $c['pos_y'] !== null ? (float)$c['pos_y'] : null,
        ];
    }

    $types = [];
    $typeIdToName = [];
    foreach ($pdo->query('SELECT * FROM rel_relation_types ORDER BY category, sort, id') as $t) {
        $types[] = [
            'name'     => $t['name'],
            'reverse'  => $t['reverse_name'],
            'category' => $t['category'],
            'directed' => (int)$t['directed'],
        ];
        $typeIdToName[(int)$t['id']] = $t['name'];
    }

    $charIdToName = [];
    foreach ($pdo->query('SELECT id, name FROM rel_characters') as $c) {
        $charIdToName[(int)$c['id']] = $c['name'];
    }
    $relations = [];
    foreach ($pdo->query('SELECT * FROM rel_relations ORDER BY id') as $r) {
        $fromName = $charIdToName[(int)$r['from_id']] ?? null;
        $toName   = $charIdToName[(int)$r['to_id']] ?? null;
        $typeName = $typeIdToName[(int)$r['type_id']] ?? null;
        if (!$fromName || !$toName || !$typeName) continue;
        $relations[] = [
            'from' => $fromName,
            'to'   => $toName,
            'type' => $typeName,
            'note' => $r['note'] ?: '',
        ];
    }

    return [
        'version'    => 1,
        'exported_at'=> date('Y-m-d H:i:s'),
        'teams'      => array_values($teams),
        'characters' => $characters,
        'types'      => $types,
        'relations'  => $relations,
    ];
}

/**
 * 导入数据。模式：
 *  - 'merge'    : 按名称匹配，存在则更新，不存在则新建（默认）
 *  - 'replace'  : 先清空所有数据，再导入
 *
 * 返回统计信息。
 */
function rel_import_data(array $data, string $mode = 'merge'): array
{
    global $pdo;

    $stats = [
        'teams_created'     => 0,
        'teams_updated'     => 0,
        'characters_created'=> 0,
        'characters_updated'=> 0,
        'types_created'     => 0,
        'types_updated'     => 0,
        'relations_created' => 0,
        'relations_updated' => 0,
        'relations_skipped' => 0,
        'errors'            => [],
    ];

    $pdo->beginTransaction();
    try {
        if ($mode === 'replace') {
            $pdo->exec('DELETE FROM rel_relations');
            $pdo->exec('DELETE FROM rel_character_teams');
            $pdo->exec('DELETE FROM rel_characters');
            $pdo->exec('DELETE FROM rel_teams');
            $pdo->exec('DELETE FROM rel_relation_types');
        }

        /* ---------- 团队 ---------- */
        $teamNameToId = [];
        foreach ($pdo->query('SELECT id, name FROM rel_teams') as $t) {
            $teamNameToId[$t['name']] = (int)$t['id'];
        }

        foreach ((array)($data['teams'] ?? []) as $t) {
            $name = trim((string)($t['name'] ?? ''));
            if ($name === '') continue;
            $color = trim((string)($t['color'] ?? ''));
            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '';

            if (isset($teamNameToId[$name])) {
                $pdo->prepare('UPDATE rel_teams SET color = ? WHERE id = ?')
                    ->execute([$color, $teamNameToId[$name]]);
                $stats['teams_updated']++;
            } else {
                $pdo->prepare('INSERT INTO rel_teams (name, color) VALUES (?, ?)')
                    ->execute([$name, $color]);
                $teamNameToId[$name] = (int)$pdo->lastInsertId();
                $stats['teams_created']++;
            }
        }

        /* ---------- 关系类型 ---------- */
        $typeNameToId = [];
        foreach ($pdo->query('SELECT id, name FROM rel_relation_types') as $t) {
            $typeNameToId[$t['name']] = (int)$t['id'];
        }

        foreach ((array)($data['types'] ?? []) as $t) {
            $name    = trim((string)($t['name'] ?? ''));
            $reverse = trim((string)($t['reverse'] ?? ''));
            $cat     = trim((string)($t['category'] ?? '其他'));
            $dir     = !empty($t['directed']) ? 1 : 0;
            if ($name === '' || $reverse === '') continue;

            if (isset($typeNameToId[$name])) {
                $pdo->prepare(
                    'UPDATE rel_relation_types SET reverse_name = ?, category = ?, directed = ? WHERE id = ?'
                )->execute([$reverse, $cat, $dir, $typeNameToId[$name]]);
                $stats['types_updated']++;
            } else {
                $pdo->prepare(
                    'INSERT INTO rel_relation_types (name, reverse_name, category, directed) VALUES (?, ?, ?, ?)'
                )->execute([$name, $reverse, $cat, $dir]);
                $typeNameToId[$name] = (int)$pdo->lastInsertId();
                $stats['types_created']++;
            }
        }

        /* ---------- 角色 ---------- */
        $charNameToId = [];
        foreach ($pdo->query('SELECT id, name FROM rel_characters') as $c) {
            $charNameToId[$c['name']] = (int)$c['id'];
        }

        $charTeamStmt = $pdo->prepare(
            'INSERT OR IGNORE INTO rel_character_teams (character_id, team_id) VALUES (?, ?)'
        );

        foreach ((array)($data['characters'] ?? []) as $c) {
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '') continue;
            $color  = trim((string)($c['color'] ?? ''));
            $avatar = trim((string)($c['avatar'] ?? ''));
            $wiki   = trim((string)($c['wiki'] ?? ''));
            $note   = trim((string)($c['note'] ?? ''));

            // 位置：JSON 里带了就接收，没带就 null
            $posX = (isset($c['x']) && is_numeric($c['x'])) ? (float)$c['x'] : null;
            $posY = (isset($c['y']) && is_numeric($c['y'])) ? (float)$c['y'] : null;

            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '';
            if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) $avatar = '';
            if ($wiki !== '' && !filter_var($wiki, FILTER_VALIDATE_URL)) $wiki = '';

            if (isset($charNameToId[$name])) {
                $cid = $charNameToId[$name];
                $pdo->prepare(
                    'UPDATE rel_characters SET color=?, avatar_url=?, wiki_url=?, note=?, pos_x=?, pos_y=? WHERE id=?'
                )->execute([
                    $color,
                    $avatar !== '' ? $avatar : null,
                    $wiki !== '' ? $wiki : null,
                    $note !== '' ? $note : null,
                    $posX,
                    $posY,
                    $cid,
                ]);
                $stats['characters_updated']++;
            } else {
                $pdo->prepare(
                    'INSERT INTO rel_characters (name, color, avatar_url, wiki_url, note, pos_x, pos_y, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $name, $color,
                    $avatar !== '' ? $avatar : null,
                    $wiki !== '' ? $wiki : null,
                    $note !== '' ? $note : null,
                    $posX,
                    $posY,
                    date('Y-m-d H:i:s'),
                ]);
                $cid = (int)$pdo->lastInsertId();
                $charNameToId[$name] = $cid;
                $stats['characters_created']++;
            }

            // 团队关联
            $teams = (array)($c['teams'] ?? []);
            if ($mode === 'replace') {
                $pdo->prepare('DELETE FROM rel_character_teams WHERE character_id = ?')->execute([$cid]);
            }
            foreach ($teams as $tname) {
                $tname = (string)$tname;
                if (isset($teamNameToId[$tname])) {
                    $charTeamStmt->execute([$cid, $teamNameToId[$tname]]);
                }
            }
        }

        /* ---------- 关系 ---------- */
        foreach ((array)($data['relations'] ?? []) as $r) {
            $fromName = trim((string)($r['from'] ?? ''));
            $toName   = trim((string)($r['to'] ?? ''));
            $typeName = trim((string)($r['type'] ?? ''));
            $note     = trim((string)($r['note'] ?? ''));

            if (!$fromName || !$toName || !$typeName) {
                $stats['errors'][] = "关系缺少 from/to/type：{$fromName} → {$toName} ({$typeName})";
                continue;
            }
            if (!isset($charNameToId[$fromName])) {
                $stats['errors'][] = "找不到角色：{$fromName}";
                continue;
            }
            if (!isset($charNameToId[$toName])) {
                $stats['errors'][] = "找不到角色：{$toName}";
                continue;
            }
            if (!isset($typeNameToId[$typeName])) {
                $stats['errors'][] = "找不到关系类型：{$typeName}";
                continue;
            }

            $fromId = $charNameToId[$fromName];
            $toId   = $charNameToId[$toName];
            $typeId = $typeNameToId[$typeName];

            if ($fromId === $toId) {
                $stats['relations_skipped']++;
                continue;
            }

            // 查重
            $stmt = $pdo->prepare(
                'SELECT id FROM rel_relations WHERE from_id=? AND to_id=? AND type_id=? LIMIT 1'
            );
            $stmt->execute([$fromId, $toId, $typeId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $pdo->prepare('UPDATE rel_relations SET note=? WHERE id=?')
                    ->execute([$note !== '' ? $note : null, $existing]);
                $stats['relations_updated']++;
            } else {
                $pdo->prepare(
                    'INSERT INTO rel_relations (from_id, to_id, type_id, note, created_at)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $fromId, $toId, $typeId,
                    $note !== '' ? $note : null,
                    date('Y-m-d H:i:s'),
                ]);
                $stats['relations_created']++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $stats;
}
/**
 * 获取或自动生成 API token
 */
function rel_api_token(): string
{
    $token = setting('rel_api_token', '');
    if ($token === '') {
        $token = bin2hex(random_bytes(24));
        set_setting('rel_api_token', $token);
    }
    return $token;
}

/**
 * 重新生成 API token
 */
function rel_regenerate_api_token(): string
{
    $token = bin2hex(random_bytes(24));
    set_setting('rel_api_token', $token);
    return $token;
}

function rel_page_title(): string
{
    $t = setting('rel_page_title', '');
    if ($t === '') return '关系图';
    return $t;
}