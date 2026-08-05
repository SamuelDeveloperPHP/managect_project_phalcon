<?php

declare(strict_types=1);

use Phalcon\Mvc\Router;

return static function (): Router {
    $router = new Router(false);
    $router->removeExtraSlashes(true);

    $router->addGet('/', [
        'controller' => 'dashboard',
        'action' => 'index',
    ])->setName('home');

    $router->addGet('/login', [
        'controller' => 'auth',
        'action' => 'login',
    ])->setName('login.form');

    $router->addPost('/login', [
        'controller' => 'auth',
        'action' => 'authenticate',
    ])->setName('login.authenticate');

    $router->addGet('/dashboard', [
        'controller' => 'dashboard',
        'action' => 'index',
    ])->setName('dashboard');

    $router->addPost('/logout', [
        'controller' => 'auth',
        'action' => 'logout',
    ])->setName('logout');

    $router->addGet('/gantt', ['controller' => 'gantt', 'action' => 'index']);
    $router->addGet('/projects', ['controller' => 'projects', 'action' => 'index']);
    $router->addGet('/projects/create', ['controller' => 'projects', 'action' => 'create']);
    $router->addPost('/projects', ['controller' => 'projects', 'action' => 'store']);
    $router->addGet('/projects/{id:[0-9]+}/edit', ['controller' => 'projects', 'action' => 'edit']);
    $router->addPost('/projects/{id:[0-9]+}', ['controller' => 'projects', 'action' => 'update']);
    $router->addPost('/projects/{id:[0-9]+}/delete', ['controller' => 'projects', 'action' => 'delete']);
    $router->addGet('/projects/{id:[0-9]+}/gantt', ['controller' => 'gantt', 'action' => 'project']);
    $router->addGet('/api/projects/{id:[0-9]+}/gantt', ['controller' => 'ganttApi', 'action' => 'index']);
    $router->addPost('/api/projects/{id:[0-9]+}/gantt', ['controller' => 'ganttApi', 'action' => 'save']);

    $router->addGet('/users', ['controller' => 'users', 'action' => 'index']);
    $router->addGet('/api/users', ['controller' => 'usersApi', 'action' => 'index']);
    $router->addPost('/api/users', ['controller' => 'usersApi', 'action' => 'create']);
    $router->addPut('/api/users/{id:[0-9]+}', ['controller' => 'usersApi', 'action' => 'update']);
    $router->addPost('/api/users/{id:[0-9]+}/block', ['controller' => 'usersApi', 'action' => 'block']);
    $router->addPost('/api/users/{id:[0-9]+}/unblock', ['controller' => 'usersApi', 'action' => 'unblock']);
    $router->addDelete('/api/users/{id:[0-9]+}', ['controller' => 'usersApi', 'action' => 'delete']);
    $router->addGet('/api/audits', ['controller' => 'usersApi', 'action' => 'audits']);

    // Multi-tenant Company Profile & Auto-lookup Routes
    $router->addGet('/companies/profile', ['controller' => 'companies', 'action' => 'profile']);
    $router->addPost('/companies/profile/update', ['controller' => 'companies', 'action' => 'updateProfile']);
    $router->addGet('/companies/cnpj-lookup', ['controller' => 'companies', 'action' => 'cnpjLookup']);
    $router->addGet('/companies/cep-lookup', ['controller' => 'companies', 'action' => 'cepLookup']);

    $router->notFound([
        'controller' => 'error',
        'action' => 'notFound',
    ]);

    return $router;
};
