<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Phalcon\Mvc\Dispatcher;
use Throwable;

final class UsersApiController extends ControllerBase
{
    protected bool $requiresAdmin = true;

    public function beforeExecuteRoute(Dispatcher $d): bool
    {
        $this->view->disable();
        return parent::beforeExecuteRoute($d);
    }

    public function indexAction()
    {
        try {
            $q = trim((string)$this->request->getQuery('q'));
            $page = max(1, (int)$this->request->getQuery('page'));
            $limit = 10;
            [$where, $bind] = $this->userScope();

            if ($q !== '') {
                $where .= ' AND (name LIKE :q: OR email LIKE :q: OR cpf LIKE :q:)';
                $bind['q'] = '%' . $q . '%';
            }

            $total = User::count(['conditions' => $where, 'bind' => $bind]);
            $rows = User::find([
                'conditions' => $where,
                'bind' => $bind,
                'order' => 'id DESC',
                'limit' => ['number' => $limit, 'offset' => ($page - 1) * $limit],
            ]);

            $items = [];
            foreach ($rows as $u) {
                $items[] = $this->item($u);
            }

            $response = [
                'users' => $items,
                'total' => (int)$total,
                'page' => $page,
                'pages' => max(1, (int)ceil($total / $limit)),
            ];

            if ($this->isMasterUser()) {
                $response['companies'] = $this->companies();
            }

            return $this->ok($response);
        } catch (Throwable $e) {
            return $this->fail('users.list', $e);
        }
    }

    public function createAction()
    {
        try {
            $p = $this->payload();
            $companyId = $this->targetCompanyId($p);
            $companyDomain = $this->companyDomain($companyId);

            $this->validate($p, true, $companyDomain, null);

            $permissions = is_array($p['permissions'] ?? null) ? $p['permissions'] : [];

            $u = new User();
            $u->assign([
                'company_id' => $companyId,
                'name' => trim($p['name']),
                'email' => strtolower(trim($p['email'])),
                'cpf' => $this->cpfDigits((string)($p['cpf'] ?? '')),
                'password' => password_hash($p['password'], PASSWORD_DEFAULT),
                'role' => $this->roleValue($p['role'] ?? 'user'),
                'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                'is_active' => 1,
            ]);

            $this->db->begin();
            if (!$u->create()) {
                throw new \RuntimeException('Não foi possível criar o usuário. Verifique se o e-mail já existe.');
            }

            $this->audit('user_created', 'users', (int)$u->id, 'Usuário criado', ['email' => $u->email]);
            $this->db->commit();

            return $this->ok(['user' => $this->item($u), 'message' => 'Usuário criado com sucesso.'], 201);
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            return $this->fail('users.create', $e, 422);
        }
    }

    public function updateAction(int $id)
    {
        try {
            $p = $this->payload();
            $u = $this->find($id);
            $this->protectMasterAccount($u);
            $companyDomain = $this->companyDomain((int)$u->company_id);
            $this->validate($p, false, $companyDomain, $id);

            $u->name = trim($p['name']);
            $u->email = strtolower(trim($p['email']));
            $u->cpf = $this->cpfDigits((string)($p['cpf'] ?? ''));
            $u->role = $this->roleValue($p['role'] ?? 'user', $u);

            if (isset($p['permissions']) && is_array($p['permissions'])) {
                $u->permissions = json_encode($p['permissions'], JSON_UNESCAPED_UNICODE);
            }

            if (!empty($p['password'])) {
                $u->password = password_hash($p['password'], PASSWORD_DEFAULT);
            }

            $this->db->begin();
            if (!$u->save()) {
                throw new \RuntimeException('Não foi possível atualizar o usuário.');
            }

            $this->audit('user_updated', 'users', $id, 'Usuário atualizado', ['email' => $u->email]);
            $this->db->commit();

            return $this->ok(['user' => $this->item($u), 'message' => 'Usuário atualizado.']);
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            return $this->fail('users.update', $e, 422);
        }
    }

    public function blockAction(int $id)
    {
        return $this->status($id, false);
    }

    public function unblockAction(int $id)
    {
        return $this->status($id, true);
    }

    public function deleteAction(int $id)
    {
        try {
            $this->requireCsrfToken();
            $this->protectSelf($id);
            $u = $this->find($id);
            $this->protectMasterAccount($u);

            $this->db->begin();
            $u->is_active = 0;
            $u->deleted_at = date('Y-m-d H:i:s');
            if (!$u->save()) {
                throw new \RuntimeException('Não foi possível excluir o usuário.');
            }

            $this->audit('user_deleted', 'users', $id, 'Usuário excluído', ['email' => $u->email]);
            $this->db->commit();

            return $this->ok(['message' => 'Usuário excluído.']);
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            return $this->fail('users.delete', $e, 422);
        }
    }

    public function auditsAction()
    {
        try {
            [$where, $bind] = $this->auditScope();
            $rows = AuditLog::find([
                'conditions' => $where,
                'bind' => $bind,
                'order' => 'id DESC',
                'limit' => 50,
            ]);

            $items = [];
            foreach ($rows as $a) {
                $items[] = [
                    'id' => (int)$a->id,
                    'user_id' => $a->user_id ? (int)$a->user_id : null,
                    'action' => $a->action,
                    'description' => $a->description,
                    'created_at' => $a->created_at,
                ];
            }

            return $this->ok(['audits' => $items]);
        } catch (Throwable $e) {
            return $this->fail('audits.list', $e);
        }
    }

    private function status(int $id, bool $active)
    {
        try {
            $this->requireCsrfToken();
            $this->protectSelf($id);
            $u = $this->find($id);
            $this->protectMasterAccount($u);

            $this->db->begin();
            $u->is_active = $active ? 1 : 0;
            if (!$u->save()) {
                throw new \RuntimeException('Não foi possível alterar o acesso.');
            }

            $this->audit($active ? 'user_unblocked' : 'user_blocked', 'users', $id, $active ? 'Usuário desbloqueado' : 'Usuário bloqueado', ['email' => $u->email]);
            $this->db->commit();

            return $this->ok(['message' => $active ? 'Usuário desbloqueado.' : 'Usuário bloqueado.']);
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            return $this->fail('users.status', $e, 422);
        }
    }

    private function item(User $u): array
    {
        return [
            'id' => (int)$u->id,
            'company_id' => (int)$u->company_id,
            'company_name' => $u->company ? (string)$u->company->name : '',
            'name' => $u->name,
            'email' => $u->email,
            'cpf' => $u->cpf,
            'role' => $u->role,
            'permissions' => $u->getPermissionsArray(),
            'is_active' => (bool)$u->is_active,
            'last_seen_at' => $u->last_seen_at,
            'is_online' => (bool)$u->is_active && $u->last_seen_at && strtotime($u->last_seen_at) >= time() - 300,
            'created_at' => $u->created_at,
        ];
    }

    private function payload(): array
    {
        return (array)$this->request->getJsonRawBody(true);
    }

    private function validate(array $p, bool $password, string $companyDomain, ?int $ignoreUserId): void
    {
        $this->requireCsrfToken();

        if (strlen(trim($p['name'] ?? '')) < 3) {
            throw new \RuntimeException('Informe um nome válido.');
        }

        $email = strtolower(trim($p['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Informe um e-mail válido.');
        }

        $userDomain = Company::extractDomain($email);
        if ($companyDomain !== '' && $userDomain !== $companyDomain) {
            throw new \RuntimeException("O e-mail do usuário deve possuir o mesmo domínio da empresa (@{$companyDomain}).");
        }

        $cpf = $this->cpfDigits((string)($p['cpf'] ?? ''));
        if (!$this->isValidCpf($cpf)) {
            throw new \RuntimeException('Informe um CPF válido para o usuário.');
        }

        $this->ensureUniqueUserIdentity($email, $cpf, $ignoreUserId);

        if (($password || !empty($p['password'])) && strlen($p['password'] ?? '') < 8) {
            throw new \RuntimeException('A senha deve ter pelo menos 8 caracteres.');
        }
    }

    private function requireCsrfToken(): void
    {
        if (!$this->hasValidCsrfToken()) {
            throw new \RuntimeException('Token de segurança inválido.');
        }
    }

    private function find(int $id): User
    {
        if ($this->isMasterUser()) {
            $u = User::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $id],
            ]);
        } else {
            $companyId = $this->currentCompanyId();
            $u = User::findFirst([
                'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL AND role <> :master_role:',
                'bind' => ['id' => $id, 'company_id' => $companyId, 'master_role' => 'master'],
            ]);
        }

        if (!$u instanceof User) {
            throw new \RuntimeException('Usuário não encontrado.');
        }

        return $u;
    }

    private function protectSelf(int $id): void
    {
        $a = $this->session->get('auth');
        if (is_array($a) && (int)($a['id'] ?? 0) === $id) {
            throw new \RuntimeException('Você não pode bloquear ou excluir sua própria conta.');
        }
    }

    private function protectMasterAccount(User $user): void
    {
        if ((string)$user->role === 'master' && !$this->isMasterUser()) {
            throw new \RuntimeException('Somente um usuário master pode alterar outro usuário master.');
        }
    }

    private function userScope(): array
    {
        if ($this->isMasterUser()) {
            return ['deleted_at IS NULL', []];
        }

        return [
            'company_id = :company_id: AND deleted_at IS NULL AND role <> :master_role:',
            ['company_id' => $this->currentCompanyId(), 'master_role' => 'master'],
        ];
    }

    private function auditScope(): array
    {
        if ($this->isMasterUser()) {
            return ['1 = 1', []];
        }

        return [
            'company_id = :company_id:',
            ['company_id' => $this->currentCompanyId()],
        ];
    }

    private function targetCompanyId(array $payload): int
    {
        $companyId = $this->isMasterUser()
            ? (int)($payload['company_id'] ?? $this->currentCompanyId())
            : $this->currentCompanyId();

        $this->companyDomain($companyId);

        return $companyId;
    }

    private function companyDomain(int $companyId): string
    {
        $company = Company::findFirst([
            'conditions' => 'id = :id: AND deleted_at IS NULL',
            'bind' => ['id' => $companyId],
        ]);

        if (!$company instanceof Company) {
            throw new \RuntimeException('Empresa não encontrada para vincular o usuário.');
        }

        $this->requireCompanyAccess((int)$company->id);

        return strtolower((string)$company->domain);
    }

    private function companies(): array
    {
        $rows = Company::find([
            'conditions' => 'deleted_at IS NULL',
            'order' => 'name ASC',
        ]);

        $items = [];
        foreach ($rows as $company) {
            $items[] = [
                'id' => (int)$company->id,
                'name' => (string)$company->name,
                'domain' => (string)$company->domain,
            ];
        }

        return $items;
    }

    private function roleValue(mixed $value, ?User $current = null): string
    {
        $role = (string)$value;
        if ($role === 'master' && $this->isMasterUser()) {
            return 'master';
        }

        if ($current instanceof User && (string)$current->role === 'master') {
            return 'master';
        }

        return in_array($role, ['admin', 'user'], true) ? $role : 'user';
    }

    private function ensureUniqueUserIdentity(string $email, string $cpf, ?int $ignoreUserId): void
    {
        $conditions = 'email = :email:';
        $bind = ['email' => $email];
        if ($ignoreUserId !== null) {
            $conditions .= ' AND id <> :id:';
            $bind['id'] = $ignoreUserId;
        }

        if (User::findFirst(['conditions' => $conditions, 'bind' => $bind]) instanceof User) {
            throw new \RuntimeException('Já existe um usuário cadastrado com este e-mail.');
        }

        $conditions = 'cpf = :cpf:';
        $bind = ['cpf' => $cpf];
        if ($ignoreUserId !== null) {
            $conditions .= ' AND id <> :id:';
            $bind['id'] = $ignoreUserId;
        }

        if (User::findFirst(['conditions' => $conditions, 'bind' => $bind]) instanceof User) {
            throw new \RuntimeException('Já existe um usuário cadastrado com este CPF.');
        }
    }

    private function cpfDigits(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf) ?? '';
    }

    private function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int)$cpf[$i] * (($t + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function ok(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJsonContent(['success' => true] + $data);
    }

    private function fail(string $ctx, Throwable $e, int $status = 500)
    {
        $this->logError($ctx, $e);
        return $this->response->setStatusCode($status)->setJsonContent(['success' => false, 'message' => $status === 500 ? 'Ocorreu um erro interno.' : $e->getMessage()]);
    }
}
