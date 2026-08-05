<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Throwable;

abstract class ControllerBase extends Controller
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = false;
    protected ?string $requiredPermission = null;

    public function beforeExecuteRoute(Dispatcher $dispatcher): bool
    {
        if (!$this->requiresAuthentication) {
            return true;
        }

        $auth = $this->session->get('auth');
        $userId = is_array($auth) ? (int)($auth['id'] ?? 0) : 0;

        $user = $userId > 0
            ? User::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $userId],
            ])
            : null;

        if (!$user instanceof User || !(int)$user->is_active) {
            return $this->deny(401, 'Sua sessão expirou ou o acesso foi bloqueado.');
        }

        $company = $user->company;
        if (!$company instanceof Company || $company->deleted_at !== null) {
            return $this->deny(403, 'A empresa vinculada ao seu usuário está inativa ou foi desativada.');
        }

        // Check Admin requirement
        if ($this->requiresAdmin && $user->role !== 'admin' && $user->role !== 'master') {
            return $this->deny(403, 'Acesso permitido somente para administradores.');
        }

        // Check specific permission requirement if defined
        if ($this->requiredPermission !== null && !$user->hasPermission($this->requiredPermission)) {
            return $this->deny(403, 'Você não possui permissão para acessar esta funcionalidade.');
        }

        if (!$user->last_seen_at || strtotime((string)$user->last_seen_at) < time() - 60) {
            $user->last_seen_at = date('Y-m-d H:i:s');
            $user->save();
        }

        $this->session->set('auth', [
            'id' => (int)$user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_id' => (int)$company->id,
            'company_name' => $company->name,
            'company_domain' => $company->domain,
            'company_logo' => $company->logo_path,
            'permissions' => $user->getPermissionsArray(),
        ]);

        return true;
    }

    protected function currentCompanyId(): int
    {
        $auth = $this->session->get('auth');
        return is_array($auth) ? (int)($auth['company_id'] ?? 0) : 0;
    }

    protected function hasPermission(string $permissionKey): bool
    {
        $auth = $this->session->get('auth');
        if (!is_array($auth)) {
            return false;
        }

        if (($auth['role'] ?? '') === 'admin' || ($auth['role'] ?? '') === 'master') {
            return true;
        }

        $perms = $auth['permissions'] ?? [];
        return !empty($perms[$permissionKey]);
    }

    private function deny(int $status, string $message): bool
    {
        $this->session->remove('auth');

        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            $this->view->disable();
            $this->response->setStatusCode($status)->setJsonContent([
                'success' => false,
                'message' => $message,
            ]);
        } else {
            $this->flashSession->error($message);
            $this->response->redirect('/login');
        }

        return false;
    }

    protected function csrfToken(): string
    {
        $t = $this->session->get('csrf_token');
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $t);
        }

        return $t;
    }

    protected function refreshCsrfToken(): string
    {
        $t = bin2hex(random_bytes(32));
        $this->session->set('csrf_token', $t);
        return $t;
    }

    protected function hasValidCsrfToken(): bool
    {
        $a = $this->session->get('csrf_token');
        $b = $this->request->getHeader('X-CSRF-Token') ?: $this->request->getPost('csrf_token');
        return is_string($a) && is_string($b) && $a !== '' && hash_equals($a, $b);
    }

    protected function audit(string $action, string $type, ?int $entityId, string $description, array $metadata = []): void
    {
        $auth = $this->session->get('auth');
        $log = new AuditLog();
        $log->assign([
            'user_id' => is_array($auth) ? (int)($auth['id'] ?? 0) : null,
            'company_id' => is_array($auth) ? (int)($auth['company_id'] ?? 0) : null,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $this->request->getClientAddress(),
            'user_agent' => substr((string)$this->request->getUserAgent(), 0, 255),
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);

        if (!$log->create()) {
            throw new \RuntimeException('Não foi possível registrar a auditoria.');
        }

        $this->db->execute('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    }

    protected function logError(string $context, Throwable $e): void
    {
        error_log('[' . $context . '] ' . preg_replace('/\s+/', ' ', trim($e->getMessage())));
    }
}
