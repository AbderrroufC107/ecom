<?php
$json = file_get_contents('https://documenter.gw.postman.com/api/collections/14517169/Tz5je15g?segregateAuth=true&versionTag=latest');
file_put_contents('ecotrack_dump.json', json_encode(json_decode($json), JSON_PRETTY_PRINT));
