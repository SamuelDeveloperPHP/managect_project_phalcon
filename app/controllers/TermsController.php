<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

// Portão de aceite dos Termos de Uso. Exige login, mas NÃO é bloqueado pelo
// próprio portão (senão haveria loop de redirecionamento).
final class TermsController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresTermsAcceptance = false;

    public function acceptAction()
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->response->redirect('/login');
        }

        // Já aceitou a versão vigente? Não precisa ver esta página.
        if (!empty($user->terms_accepted_at) && $user->terms_version === self::CURRENT_TERMS_VERSION) {
            return $this->response->redirect('/dashboard');
        }

        $this->view->setVars([
            'csrfToken' => $this->csrfToken(),
            'version' => self::CURRENT_TERMS_VERSION,
            'userName' => (string) ($user->name ?? ''),
        ]);
    }

    public function storeAction()
    {
        if (!$this->request->isPost() || !$this->hasValidCsrfToken()) {
            $this->flashSession->error('A sessão do formulário expirou. Tente novamente.');
            return $this->response->redirect('/termos/aceite');
        }

        if (!$this->request->getPost('accept')) {
            $this->flashSession->error('É necessário marcar o aceite para continuar.');
            return $this->response->redirect('/termos/aceite');
        }

        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->response->redirect('/login');
        }

        $ip = $this->request->getClientAddress(true);
        $now = date('Y-m-d H:i:s');

        $user->terms_accepted_at = $now;
        $user->terms_version = self::CURRENT_TERMS_VERSION;
        $user->terms_accepted_ip = is_string($ip) && $ip !== '' ? $ip : (string) $this->request->getClientAddress();
        $user->save();

        $this->audit(
            'terms_accepted',
            'users',
            (int) $user->id,
            'Aceite dos Termos de Uso e Política de Privacidade (v' . self::CURRENT_TERMS_VERSION . ')',
            ['version' => self::CURRENT_TERMS_VERSION, 'accepted_at' => $now]
        );

        $this->refreshCsrfToken();
        $this->flashSession->success('Termos aceitos. Bem-vindo(a)!');

        return $this->response->redirect('/dashboard');
    }

    private function currentUser(): ?User
    {
        $auth = $this->session->get('auth');
        $id = is_array($auth) ? (int) ($auth['id'] ?? 0) : 0;
        if ($id <= 0) {
            return null;
        }

        return User::findFirst([
            'conditions' => 'id = :id: AND deleted_at IS NULL',
            'bind' => ['id' => $id],
        ]);
    }
}
