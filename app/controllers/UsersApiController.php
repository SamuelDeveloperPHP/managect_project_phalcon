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
            $companyId = $this->currentCompanyId();
            $q = trim((string)$this->request->getQuery('q'));
            $page = max(1, (int)$this->request->getQuery('page'));
            $limit = 10;
            $where = 'company_id = :company_id: AND deleted_at IS NULL';
            $bind = ['company_id' => $companyId];

            if ($q !== '') {
                $where .= ' AND (name LIKE :q: OR email LIKE :q:)';
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

            return $this->ok([
                'users' => $items,
                'total' => (int)$total,
                'page' => $page,
                'pages' => max(1, (int)ceil($total / $limit)),
            ]);
        } catch (Throwable $e) {
            return $this->fail('users.list', $e);
        }
    }

    public function createAction()
    {
        try {
            $companyId = $this->currentCompanyId();
            $auth = $this->session->get('auth');
            $companyDomain = strtolower((string)($auth['company_domain'] ?? ''));

            $p = $this->payload();
            $this->validate($p, true, $companyDomain);

            $permissions = is_array($p['permissions'] ?? null) ? $p['permissions'] : [];

            $u = new User();
            $u->assign([
                'company_id' => $companyId,
                'name' => trim($p['name']),
                'email' => strtolower(trim($p['email'])),
                'password' => password_hash($p['password'], PASSWORD_DEFAULT),
                'role' => in_array($p['role'] ?? 'user', ['admin', 'user'], true) ? $p['role'] : 'user',
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
            $auth = $this->session->get('auth');
            $companyDomain = strtolower((string)($auth['company_domain'] ?? ''));

            $p = $this->payload();
            $this->validate($p, false, $companyDomain);

            $u = $this->find($id);
            $u->name = trim($p['name']);
            $u->email = strtolower(trim($p['email']));
            $u->role = in_array($p['role'] ?? 'user', ['admin', 'user'], true) ? $p['role'] : 'user';

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
            $this->protectSelf($id);
            $u = $this->find($id);

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
            $companyId = $this->currentCompanyId();
            $rows = AuditLog::find([
                'conditions' => 'company_id = :company_id:',
                'bind' => ['company_id' => $companyId],
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
            $this->protectSelf($id);
            $u = $this->find($id);

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
            'name' => $u->name,
            'email' => $u->email,
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

    private function validate(array $p, bool $password, string $companyDomain): void
    {
        if (!$this->hasValidCsrfToken()) {
            throw new \RuntimeException('Token de segurança inválido.');
        }

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

        if (($password || !empty($p['password'])) && strlen($p['password'] ?? '') < 8) {
            throw new \RuntimeException('A senha deve ter pelo menos 8 caracteres.');
        }
    }

    private function find(int $id): User
    {
        $companyId = $this->currentCompanyId();
        $u = User::findFirst([
            'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL',
            'bind' => ['id' => $id, 'company_id' => $companyId],
        ]);

        if (!$u instanceof User) {
            throw new \RuntimeException('Usuário não encontrado.');
        }

        return $u;
    }

    private function protectSelf(int $id): void
    {
        $a = $this->session->get('auth');
        if ((int)$a['id'] === $id) {
            throw new \RuntimeException('Você não pode bloquear ou excluir sua própria conta.');
        }
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
