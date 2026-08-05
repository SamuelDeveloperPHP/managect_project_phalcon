<?php

declare(strict_types=1);

use Phalcon\Di\FactoryDefault;

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

$config = require __DIR__ . '/config/config.php';
$container = new FactoryDefault();

(require __DIR__ . '/config/services.php')($container, $config);

return $container;
