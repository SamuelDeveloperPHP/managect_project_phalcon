<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;

final class AuthController extends ControllerBase
{
    protected bool $requiresAuthentication = false;

    public function loginAction()
    {
        if ($this->session->has('auth')) {
            return $this->response->redirect('/dashboard');
        }

        $this->view->setVar('csrfToken', $this->csrfToken());
    }

    public function authenticateAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/login');
        }

        if (!$this->hasValidCsrfToken()) {
            $this->flashSession->error('A sessão do formulário expirou. Tente novamente.');
            return $this->response->redirect('/login');
        }

        $email = strtolower(trim((string)$this->request->getPost('email')));
        $password = (string)$this->request->getPost('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->flashSession->error('Informe e-mail e senha válidos.');
            return $this->response->redirect('/login');
        }

        $user = User::findFirst([
            'conditions' => 'email = :email: AND is_active = 1 AND deleted_at IS NULL',
            'bind' => ['email' => $email],
        ]);

        if (!$user instanceof User || !password_verify($password, $user->password)) {
            $this->flashSession->error('E-mail ou senha inválidos.');
            return $this->response->redirect('/login');
        }

        $company = $user->company;
        if (!$company instanceof Company || $company->deleted_at !== null) {
            $this->flashSession->error('A empresa vinculada a este usuário está inativa.');
            return $this->response->redirect('/login');
        }

        $this->session->regenerateId(true);
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
        $this->refreshCsrfToken();

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_seen_at = date('Y-m-d H:i:s');
        $user->save();
        $this->audit('login', 'users', (int)$user->id, 'Login realizado');

        $this->flashSession->success('Login realizado com sucesso.');

        return $this->response->redirect('/dashboard');
    }

    public function logoutAction()
    {
        if (!$this->request->isPost() || !$this->hasValidCsrfToken()) {
            $this->flashSession->error('Não foi possível validar o logout.');
            return $this->response->redirect('/dashboard');
        }

        $auth = $this->session->get('auth');
        $this->audit('logout', 'users', is_array($auth) ? (int)$auth['id'] : null, 'Logout realizado');
        $this->session->remove('auth');
        $this->session->regenerateId(true);
        $this->refreshCsrfToken();
        $this->flashSession->success('Você saiu do sistema.');

        return $this->response->redirect('/login');
    }
}
