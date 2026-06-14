<?php
$json = file_get_contents('ecotrack_dump.json');
$data = json_decode($json, true);
foreach($data['item'] as $i){
    if(isset($i['item'])){
        foreach($i['item'] as $j){
            if(strpos(strtolower($j['name']), 'plusieurs') !== false){
                echo json_encode($j['response'], JSON_PRETTY_PRINT);
            }
        }
    }
}
