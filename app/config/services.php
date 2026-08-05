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

    $container->setShared('session', function (): Manager {
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
