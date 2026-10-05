<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$n = $pdo->exec("DELETE FROM messages WHERE user_agent = 'seed'");
echo "deleted $n rows\n";