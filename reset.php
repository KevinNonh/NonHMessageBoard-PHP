<?php
require __DIR__ . '/includes/bootstrap.php';
set_setting('admin_username', 'admin');
set_setting('admin_password_hash', password_hash('admin123', PASSWORD_DEFAULT));
echo 'reset done';