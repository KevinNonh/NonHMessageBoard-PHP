<?php
declare(strict_types=1);

return [
    // 首次运行时的默认值，之后可在后台修改
    'site_name' => '留言板',
    'site_icon' => '💬',
    'timezone'  => 'Asia/Shanghai',

    'db_path' => __DIR__ . '/storage/message_board.sqlite',

    // 首次初始化用，之后可在后台修改密码
    'admin_username'      => 'admin',
    'admin_password'      => 'admin123',
    'admin_password_hash' => '',

    'bubble_limit' => 30,
	'load_batch'    => 10,   // 点一次“加载更多”追加多少条
	'post_cooldown' => 60,   // 同一 IP 两次留言的最小间隔（秒）
];