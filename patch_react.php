<?php
$f1 = 'admin/dist/admin-react.js';
$c1 = file_get_contents($f1);
$c1 = str_replace('[`system-health.php`].includes(e)', '[`system-health.php`,`my-earnings.php`,`employee-performance.php`,`employee-payments.php`].includes(e)', $c1);
file_put_contents($f1, $c1);

$f2 = 'admin/dist/admin-react-pagemeta-CMDNvJbo.js';
$c2 = file_get_contents($f2);
$c2 = str_replace('["system-health.php"].includes(e)', '["system-health.php","my-earnings.php","employee-performance.php","employee-payments.php"].includes(e)', $c2);
file_put_contents($f2, $c2);
echo "Patched successfully";
