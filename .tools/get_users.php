<?php
$pdo = new PDO('mysql:host=localhost;dbname=boomtsvp_boomtsvp_ecommerceweb;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT id, email, password FROM tbl_user WHERE status=1 LIMIT 5');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['id'] . ': ' . $r['email'] . ' | hash=' . substr($r['password'], 0, 20) . '...' . PHP_EOL;
}
echo PHP_EOL . 'Check if any admin password matches: admin123' . PHP_EOL;
$test = password_verify('admin123', '$2y$10$' . 'dummy');
echo 'password_verify works: ' . (function_exists('password_verify') ? 'yes' : 'no') . PHP_EOL;
