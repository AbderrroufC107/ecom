<?php
$json = file_get_contents('https://documenter.gw.postman.com/api/collections/14517169/Tz5je15g?segregateAuth=true&versionTag=latest');
$data = json_decode($json, true);

foreach ($data['collection']['item'] as $item) {
    if (isset($item['request'])) {
        echo $item['name'] . " => " . $item['request']['method'] . " " . $item['request']['url']['raw'] . "\n";
    }
}
