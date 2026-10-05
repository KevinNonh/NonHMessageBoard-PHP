<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF 校验失败，请刷新页面重试');
    }
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $msg, string $type = 'info'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function setting(string $key, string $default = ''): string
{
    global $settings;
    return isset($settings[$key]) && $settings[$key] !== ''
        ? (string)$settings[$key]
        : $default;
}

function set_setting(string $key, string $value): void
{
    global $pdo, $settings;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if ($exists) {
        $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?');
        $stmt->execute([$value, $key]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
        $stmt->execute([$key, $value]);
    }

    $settings[$key] = $value;
}

function site_favicon_url(): string
{
    $icon = setting('site_icon', '💬');
    if ($icon === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $icon)) {
        return $icon;
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
         . '<text y=".9em" font-size="90">' . $icon . '</text></svg>';
    return 'data:image/svg+xml,' . rawurlencode($svg);
}