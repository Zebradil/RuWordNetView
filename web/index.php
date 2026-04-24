<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

// When using PHP's built-in dev server, serve static files directly
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (PHP_SAPI === 'cli-server' && is_file($filename)) {
    return false;
}

/** @var \Slim\App $app */
$app = require __DIR__ . '/../app/app.php';
$app->run();
