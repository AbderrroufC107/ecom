<?php
$c = file_get_contents('C:/xampp/htdocs/ecom/admin/inc/functions.php');
echo substr_count($c, '$dbRepo->');
