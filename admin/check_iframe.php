<?php
session_start();
$_SESSION['user'] = ['id' => 1, 'full_name' => 'Admin', 'role' => 'Super Admin'];
$session_id = session_id();
session_write_close(); // Prevent lock!

$html = file_get_contents('http://localhost/ecom/admin/order-details.php?id=871&iframe=1', false, stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: PHPSESSID=' . $session_id . "\r\n"
    ]
]));
file_put_contents('iframe_output.html', $html);
echo "Done! Length: " . strlen($html);
