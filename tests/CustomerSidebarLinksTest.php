<?php
$targets = [
    'customer-profile-update.php',
    'customer-billing-shipping-update.php',
    'customer-password-update.php',
];

$missing = [];
foreach ($targets as $target) {
    if (!file_exists(__DIR__ . '/../' . $target)) {
        $missing[] = $target;
    }
}

if ($missing) {
    fwrite(STDERR, "Missing customer sidebar link targets: " . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo "Customer sidebar link targets exist: " . implode(', ', $targets) . PHP_EOL;
