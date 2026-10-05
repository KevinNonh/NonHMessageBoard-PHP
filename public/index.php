<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

// ---------- JSON API：加载更多留言 ----------
if (isset($_GET['before_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $beforeId = max(0, (int)$_GET['before_id']);
    $limit = max(1, (int)($config['load_batch'] ?? 10));

    $stmt = $pdo->prepare(
        "SELECT id, nickname, website, content, reply, replied_at, created_at
         FROM messages
         WHERE is_private = 0 AND id < ?
         ORDER BY id DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit + 1, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }

    echo json_encode([
        'messages' => $rows,
        'has_more' => $hasMore,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- JSON API：列表视图（游标分页） ----------
if (isset($_GET['list'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $perPage = 20;
    $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;

    if ($beforeId > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, nickname, website, content, reply, replied_at, created_at
             FROM messages
             WHERE is_private = 0 AND id < ?
             ORDER BY id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $perPage + 1, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, nickname, website, content, reply, replied_at, created_at
             FROM messages
             WHERE is_private = 0
             ORDER BY id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $perPage + 1, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $hasMore = count($rows) > $perPage;
    if ($hasMore) array_pop($rows);

    echo json_encode([
        'messages' => $rows,
        'has_more' => $hasMore,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // 蜜罐：填了就是机器人
    if (!empty($_POST['hp'])) {
        redirect('index.php');
    }

    $nickname = trim((string)($_POST['nickname'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $website  = trim((string)($_POST['website'] ?? ''));
    $content  = trim((string)($_POST['content'] ?? ''));
    $private  = !empty($_POST['is_private']) ? 1 : 0;

    $errors = [];
	// 频率限制：同一 IP 冷却时间内只能发一条
    $cooldown = max(0, (int)($config['post_cooldown'] ?? 60));
    if ($cooldown > 0) {
        $ip = client_ip();
        if ($ip !== '') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM messages WHERE ip = ? AND created_at > ?'
            );
            $stmt->execute([$ip, date('Y-m-d H:i:s', time() - $cooldown)]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = '发言太频繁，请 ' . $cooldown . ' 秒后再试';
            }
        }
    }
    if ($nickname === '' || mb_strlen($nickname) > 30) {
        $errors[] = '昵称必填，且不超过 30 字';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请填写有效的邮箱';
    }
    if ($website !== '') {
        if (!preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }
        if (!filter_var($website, FILTER_VALIDATE_URL) || mb_strlen($website) > 200) {
            $errors[] = '网址格式不正确';
        }
    } else {
        $website = null;
    }
    if ($content === '' || mb_strlen($content) > 1000) {
        $errors[] = '留言内容必填，且不超过 1000 字';
    }

    if ($errors) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_old'] = [
            'nickname' => $nickname,
            'email'    => $email,
            'website'  => $website ?? '',
            'content'  => $content,
            'private'  => $private,
        ];
        redirect('index.php');
    }

    $stmt = $pdo->prepare(
    'INSERT INTO messages (nickname, email, website, content, is_private, ip, user_agent, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $nickname, $email, $website, $content, $private,
        client_ip(),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        date('Y-m-d H:i:s'),
    ]);

    $newId = (int)$pdo->lastInsertId();
    flash_set('留言已发布，感谢你的分享！', 'success');
    redirect('index.php?highlight=' . $newId);
}

$limit = max(1, (int)$config['bubble_limit']);
$stmt = $pdo->prepare(
    "SELECT id, nickname, website, content, reply, replied_at, created_at
     FROM messages
     WHERE is_private = 0
     ORDER BY id DESC
     LIMIT ?"
);
$stmt->bindValue(1, $limit + 1, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

$hasMore = count($messages) > $limit;
if ($hasMore) {
    array_pop($messages);
}

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

$flash = flash_get();

require __DIR__ . '/../includes/views/home.php';