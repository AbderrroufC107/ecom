<?php
$json = file_get_contents('ecotrack_api.json');
$data = json_decode($json, true);

if (!isset($data['item'])) {
    echo "No items found in JSON\n";
    exit;
}

function find_requests($items, $prefix = '') {
    foreach ($items as $item) {
        if (isset($item['item'])) {
            find_requests($item['item'], $prefix . $item['name'] . ' > ');
        } else {
            if (isset($item['request']['url']['raw'])) {
                echo $prefix . $item['name'] . " => " . $item['request']['method'] . " " . $item['request']['url']['raw'] . "\n";
            }
        }
    }
}

find_requests($data['item']);
