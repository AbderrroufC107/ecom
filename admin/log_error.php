<?php
if (isset($_GET['err'])) {
    $log = date('Y-m-d H:i:s')
        . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ' | UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? '?')
        . ' | ' . $_GET['err'] . PHP_EOL;
    file_put_contents(__DIR__ . '/admin_js_error.log', $log, FILE_APPEND);
    echo "logged";
}
if (isset($_GET['ping'])) {
    echo json_encode([
        'ok' => true,
        'time' => date('Y-m-d H:i:s'),
        'dist' => is_dir(__DIR__ . '/dist'),
        'dist_files' => is_dir(__DIR__ . '/dist') ? count(glob(__DIR__ . '/dist/admin-react*.js')) : 0,
        'react_js' => file_exists(__DIR__ . '/dist/admin-react.js'),
    ]);
}
