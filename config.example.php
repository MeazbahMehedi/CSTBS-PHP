<?php
declare(strict_types=1);

return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'cstbs_php',
    'db_user' => 'root',
    'db_password' => '',
    'base_path' => '/CSTBS-PHP',
    'timezone' => 'Asia/Singapore',
    'production' => false,
    'secret' => '', // Generate with bin/console.php install. Preserve across upgrades.
    'mail_transport' => 'log', // smtp sends through the configured SMTP server.
    'mail_host' => '',
    'mail_port' => 587,
    'mail_username' => '',
    'mail_password' => '',
    'mail_from' => 'stadium@campus.example',
];
