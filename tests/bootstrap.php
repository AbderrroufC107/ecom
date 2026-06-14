<?php

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecom_test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('TELEGRAM_BOT_TOKEN', '');
define('ECOTRACK_API_URL', '');
define('ECOTRACK_API_KEY', '');
define('DEBUG_MODE', true);

// Load autoloader
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'autoload.php';
