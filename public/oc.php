<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/oc_functions.php';

$embed = !empty($_GET['embed']);
if ($embed) {
    header('X-Frame-Options: ALLOWALL');
    header('Content-Security-Policy: frame-ancestors *');
}

$forceTheme = (string)($_GET['theme'] ?? '');
$forceStyle = (string)($_GET['style'] ?? '');

$ocs = $pdo->query('SELECT * FROM ocs ORDER BY id')->fetchAll();

$now = time();
foreach ($ocs as &$oc) {
    $nextTs = oc_next_birthday((int)$oc['birth_month'], (int)$oc['birth_day'], $now);
    $oc['next_ts'] = $nextTs;
    $oc['is_today'] = date('Y-m-d', $nextTs) === date('Y-m-d', $now);
    $oc['age'] = oc_age_on($nextTs, $oc['birth_year'] ? (int)$oc['birth_year'] : null);
}
unset($oc);

usort($ocs, function ($a, $b) {
    if ($a['is_today'] !== $b['is_today']) return $a['is_today'] ? -1 : 1;
    if ($a['next_ts'] !== $b['next_ts']) return $a['next_ts'] - $b['next_ts'];
    return strcmp($a['name'], $b['name']);
});

$todayOcs = array_values(array_filter($ocs, fn($o) => $o['is_today']));
$otherOcs = array_values(array_filter($ocs, fn($o) => !$o['is_today']));

require __DIR__ . '/../includes/views/oc.php';