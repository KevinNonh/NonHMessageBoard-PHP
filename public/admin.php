<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$action = (string)($_GET['action'] ?? '');

/* ============================================================
 * 登录
 * ============================================================ */
if ($action === 'login') {
    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? [];
        $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - 300));

        if (count($attempts) >= 10) {
            $loginError = '尝试过于频繁，请 5 分钟后再试';
        } else {
            $attempts[] = $now;
            $_SESSION['login_attempts'] = $attempts;

            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');

            $dbUser = setting('admin_username', 'admin');
            $dbHash = setting('admin_password_hash', '');

            $userOk = hash_equals($dbUser, $u);
            $passOk = $dbHash !== '' && password_verify($p, $dbHash);

            if ($userOk && $passOk) {
                session_regenerate_id(true);
                $_SESSION['admin'] = true;
                unset($_SESSION['login_attempts']);
                redirect('admin.php');
            }

            $loginError = '用户名或密码错误';
        }
    }

    require __DIR__ . '/../includes/views/admin_login.php';
    exit;
}

/* ============================================================
 * 退出
 * ============================================================ */
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('admin.php?action=login');
}

/* ============================================================
 * 以下全部需要登录
 * ============================================================ */
require_admin();

/* ============================================================
 * 站点设置
 * ============================================================ */
if ($action === 'settings') {
    $settingErrors = [];
    $settingOld = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $newName     = trim((string)($_POST['site_name'] ?? ''));
        $newIcon     = trim((string)($_POST['site_icon'] ?? ''));
        $newTimezone = trim((string)($_POST['timezone'] ?? ''));
        $newUsername = trim((string)($_POST['admin_username'] ?? ''));
        $newPwd      = (string)($_POST['new_password'] ?? '');
        $confirmPwd  = (string)($_POST['confirm_password'] ?? '');
        $currentPwd  = (string)($_POST['current_password'] ?? '');
		$newAnnouncement = trim((string)($_POST['announcement'] ?? ''));
		$newFooterHtml = (string)($_POST['footer_html'] ?? '');

        if ($newName === '' || mb_strlen($newName) > 50) {
            $settingErrors[] = '站点名称必填，且不超过 50 字';
        }
        if (mb_strlen($newIcon) > 200) {
            $settingErrors[] = '站点图标过长';
        }
		if (mb_strlen($newAnnouncement) > 500) {
            $settingErrors[] = '公告内容不超过 500 字';
        }
		if (mb_strlen($newFooterHtml) > 2000) {
		    $settingErrors[] = '页脚内容不超过 2000 字';
		}
        if (!in_array($newTimezone, timezone_identifiers_list(), true)) {
            $settingErrors[] = '时区无效';
        }
        if ($newUsername === '' || mb_strlen($newUsername) > 50) {
            $settingErrors[] = '管理员用户名无效';
        }

        $passwordChanged = false;
        if ($newPwd !== '' || $confirmPwd !== '') {
            if ($newPwd !== $confirmPwd) {
                $settingErrors[] = '两次新密码不一致';
            } elseif (strlen($newPwd) < 6) {
                $settingErrors[] = '新密码至少 6 位';
            } else {
                $dbHash = setting('admin_password_hash', '');
                if ($dbHash === '' || !password_verify($currentPwd, $dbHash)) {
                    $settingErrors[] = '当前密码错误，无法修改密码';
                } else {
                    $passwordChanged = true;
                }
            }
        }

        if (!$settingErrors) {
            set_setting('site_name', $newName);
            set_setting('site_icon', $newIcon);
            set_setting('timezone', $newTimezone);
            set_setting('admin_username', $newUsername);
			set_setting('announcement', $newAnnouncement);
			set_setting('footer_html', $newFooterHtml);
            if ($passwordChanged) {
                set_setting('admin_password_hash', password_hash($newPwd, PASSWORD_DEFAULT));
            }
            date_default_timezone_set($newTimezone);
            flash_set('设置已保存', 'success');
            redirect('admin.php?action=settings');
        }

        $settingOld = $_POST;
    }

    require __DIR__ . '/../includes/views/admin_settings.php';
    exit;
}

/* ============================================================
 * 下载数据库
 * ============================================================ */
if ($action === 'download_db') {
    // 先把 WAL 里的数据合并回主文件
    try {
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    } catch (Throwable $e) {
        // 忽略，继续下载
    }

    $dbPath = $config['db_path'];
    if (!is_file($dbPath) || !is_readable($dbPath)) {
        http_response_code(500);
        exit('数据库文件不可读');
    }

    $filename = 'message_board-' . date('Ymd-His') . '.sqlite';

    // 清掉可能已经输出的缓冲
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($dbPath));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($dbPath);
    exit;
}

/* ============================================================
 * 回复
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reply') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $reply = trim((string)($_POST['reply'] ?? ''));

    if ($id > 0 && mb_strlen($reply) <= 1000) {
        if ($reply === '') {
            $stmt = $pdo->prepare('UPDATE messages SET reply = NULL, replied_at = NULL WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('已清除回复', 'info');
        } else {
            $stmt = $pdo->prepare('UPDATE messages SET reply = ?, replied_at = ? WHERE id = ?');
            $stmt->execute([$reply, date('Y-m-d H:i:s'), $id]);
            flash_set('回复已保存', 'success');
        }
    }
    redirect('admin.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

/* ============================================================
 * 编辑留言
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    csrf_check();

    $id       = (int)($_POST['id'] ?? 0);
    $nickname = trim((string)($_POST['nickname'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $website  = trim((string)($_POST['website'] ?? ''));
    $content  = trim((string)($_POST['content'] ?? ''));
    $private  = !empty($_POST['is_private']) ? 1 : 0;

    $errors = [];
    if ($nickname === '' || mb_strlen($nickname) > 30) {
        $errors[] = '昵称必填，且不超过 30 字';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确';
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
        $errors[] = '内容必填，且不超过 1000 字';
    }

    if ($errors) {
        flash_set(implode('；', $errors), 'error');
    } elseif ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE messages
             SET nickname = ?, email = ?, website = ?, content = ?, is_private = ?
             WHERE id = ?'
        );
        $stmt->execute([$nickname, $email, $website, $content, $private, $id]);
        flash_set('留言已更新', 'success');
    }

    redirect('admin.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

/* ============================================================
 * 删除
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('留言已删除', 'success');
    }
    redirect('admin.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

/* ============================================================
 * 留言列表
 * ============================================================ */
$filter = (string)($_GET['filter'] ?? 'all');
$where = '';
switch ($filter) {
    case 'pending': $where = "WHERE reply IS NULL OR reply = ''"; break;
    case 'replied': $where = "WHERE reply IS NOT NULL AND reply != ''"; break;
    case 'private': $where = "WHERE is_private = 1"; break;
    default:        $filter = 'all';
}

// 分页
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$total = (int)$pdo->query("SELECT COUNT(*) FROM messages $where")->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM messages $where ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

$flash = flash_get();

require __DIR__ . '/../includes/views/admin_messages.php';