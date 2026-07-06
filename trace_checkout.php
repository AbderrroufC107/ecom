<?php
$f = file_get_contents('landing_page_2.php');
$checks = array(
    'orderForm', 'customer_name', 'customer_phone', 'submit',
    'btn-buy-now', 'delivery', 'desk_container', 'footer',
    '</form>', '</main>', '</body>', '</html>',
    'payment-method', 'checkout-card', 'section'
);
foreach ($checks as $s) {
    $pos = strpos($f, $s);
    if ($pos !== false) {
        $line = substr_count(substr($f, 0, $pos), "\n") + 1;
        echo "$s : FOUND at line $line" . PHP_EOL;
    } else {
        echo "$s : MISSING" . PHP_EOL;
    }
}

// Count conditionals around checkout
echo PHP_EOL . "=== Conditionals before orderForm ===" . PHP_EOL;
$formPos = strpos($f, 'orderForm');
$before = substr($f, [0, $formPos - 5000], 5000);
$lines = explode("\n", $before);
$lineNum = substr_count(substr($f, 0, $formPos), "\n") + 1 - count($lines);
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (preg_match('/^\s*(if|else|elseif|endif|end\s*if|return|exit|continue|break|switch|case|default)\b/', $trimmed) ||
        preg_match('/\b(endif|end\s*if)\b/', $trimmed)) {
        $lineNum++;
        echo "Line $lineNum: $trimmed" . PHP_EOL;
    } else {
        $lineNum++;
    }
}
