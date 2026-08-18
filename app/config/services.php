<?php

declare(strict_types=1);

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di\DiInterface;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Html\Escaper;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Session\Manager;

return static function (DiInterface $container, array $config): void {
    $container->setShared('dispatcher', function (): Dispatcher {
        $dispatcher = new Dispatcher();
        $dispatcher->setDefaultNamespace('App\\Controllers');

        return $dispatcher;
    });

    $container->setShared(
        'router',
        fn () => (require __DIR__ . '/routes.php')()
    );

    $container->setShared('view', function () use ($config): View {
        $view = new View();
        $view->setViewsDir($config['paths']['views']);

        return $view;
    });

    $container->setShared(
        'db',
        fn (): Mysql => new Mysql($config['database'])
    );

    $container->setShared('redis', function () use ($config): \Redis {
        $redis = new \Redis();
        $redis->connect($config['redis']['host'], $config['redis']['port'], 1.5);

        return $redis;
    });

    $container->setShared('session', function (): Manager {
        // Endurece o cookie de sessão antes do start. Secure só sob HTTPS
        // (detecta o proxy Caddy via X-Forwarded-Proto) para não quebrar o dev em HTTP.
        $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax',
        ]);

        $session = new Manager(['uniqueId' => 'phalcon-auth']);
        $session->setAdapter(new Stream(['savePath' => '/tmp']));
        $session->setName('phalcon_auth')->start();

        return $session;
    });

    $container->setShared(
        'flashSession',
        fn () => new FlashSession(
            new Escaper(),
            $container->getShared('session')
        )
    );
};
