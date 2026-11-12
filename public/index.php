<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// On the cPanel host the app lives in a subfolder (goviraju.lk/JD_vegetables_backend) and
// the root .htaccess rewrites every request into public/. The web server then reports the
// script as .../public/index.php while the browser URL has no /public, so Laravel cannot
// work out the base path and answers 404 to every route. Present the script as living at
// the folder root instead. Requests that really use /public/ in the URL are left alone.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestPath = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), '/') . '/';
if (str_ends_with($scriptName, '/public/index.php') && ! str_starts_with($requestPath, dirname($scriptName) . '/')) {
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = substr($scriptName, 0, -strlen('public/index.php')) . 'index.php';
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
