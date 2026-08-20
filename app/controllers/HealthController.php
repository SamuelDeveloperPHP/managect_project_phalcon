<?php

declare(strict_types=1);

namespace App\Controllers;

use Throwable;

final class HealthController extends ControllerBase
{
    protected bool $requiresAuthentication = false;
    protected bool $requiresTermsAcceptance = false;

    public function healthAction()
    {
        $this->view->disable();

        return $this->response->setJsonContent([
            'status' => 'ok',
            'service' => 'managect',
        ]);
    }

    public function readyAction()
    {
        $this->view->disable();

        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $ready = !in_array(false, $checks, true);
        return $this->response
            ->setStatusCode($ready ? 200 : 503)
            ->setJsonContent([
                'status' => $ready ? 'ready' : 'degraded',
                'checks' => array_map(
                    static fn (bool $ok): string => $ok ? 'ok' : 'fail',
                    $checks
                ),
            ]);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->db->fetchOne('SELECT 1');
            return true;
        } catch (Throwable $e) {
            $this->logError('health.database', $e);
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            return (string)$this->getDI()->getShared('redis')->ping() !== '';
        } catch (Throwable $e) {
            $this->logError('health.redis', $e);
            return false;
        }
    }
}
