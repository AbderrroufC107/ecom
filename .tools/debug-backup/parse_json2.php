<?php
$json = file_get_contents('ecotrack_dump.json');
$data = json_decode($json, true);

function search_items($items, $path = "") {
    foreach($items as $i) {
        if (isset($i['item'])) {
            search_items($i['item'], $path . $i['name'] . " > ");
        } else {
            $name = strtolower($i['name']);
            if (strpos($name, 'suivi') !== false || strpos($name, 'suivre') !== false || strpos($name, 'tracking') !== false || strpos($name, 'info') !== false) {
                $url = "";
                if (isset($i['request']['url']['raw'])) {
                    $url = $i['request']['url']['raw'];
                } elseif (isset($i['request']['url']) && is_string($i['request']['url'])) {
                    $url = $i['request']['url'];
                }
                echo $path . $i['name'] . " => " . ($i['request']['method'] ?? 'GET') . " " . $url . "\n";
            }
        }
    }
}
search_items($data['item']);
