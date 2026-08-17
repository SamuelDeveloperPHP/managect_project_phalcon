<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController extends ControllerBase
{
    protected bool $requiresAuthentication = false;

    public function indexAction(): void
    {
        $loggedIn = $this->session->has('auth');
        $isDevelopment = $this->isDevelopmentEnvironment();

        $this->view->setVars([
            'loggedIn' => $loggedIn,
            'isDevelopment' => $isDevelopment,
            'ctaHref' => $loggedIn ? '/dashboard' : ($isDevelopment ? '/register' : '/login'),
            'ctaLabel' => $loggedIn ? 'Abrir painel' : ($isDevelopment ? 'Criar workspace' : 'Entrar'),
        ]);
    }

    private function isDevelopmentEnvironment(): bool
    {
        $environment = strtolower((string)(getenv('APP_ENV') ?: ''));
        if (in_array($environment, ['development', 'dev', 'local'], true)) {
            return true;
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }
}
