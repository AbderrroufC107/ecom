<?php
$content = file_get_contents('C:/xampp/htdocs/ecom/admin/inc/config.php');
$content = str_replace("strpos(\$class, 'SaaS\Repositories\')", "strpos(\$class, 'SaaS\\\\Repositories\\\\')", $content);
$content = str_replace("str_replace('\', '/', \$class)", "str_replace('\\\\', '/', \$class)", $content);

$dbRepoStr = <<<PHP
\$employeeRepo = new \SaaS\Repositories\EmployeeRepository(\$pdo);
global \$dbRepo;
\$dbRepo = new \SaaS\Repositories\DatabaseRepository(\$pdo);
PHP;
$content = str_replace("\$employeeRepo = new \SaaS\Repositories\EmployeeRepository(\$pdo);", $dbRepoStr, $content);

file_put_contents('C:/xampp/htdocs/ecom/admin/inc/config.php', $content);
