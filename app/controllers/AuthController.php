<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;
use Throwable;

final class AuthController extends ControllerBase
{
    protected bool $requiresAuthentication = false;

    /** Rate limit de login (anti brute force). Janela e limites por IP e por e-mail. */
    private const LOGIN_MAX_PER_IP = 12;
    private const LOGIN_MAX_PER_EMAIL = 6;
    private const LOGIN_WINDOW_SECONDS = 900; // 15 minutos

    public function loginAction()
    {
        if ($this->session->has('auth')) {
            return $this->response->redirect('/dashboard');
        }

        $this->view->setVars([
            'csrfToken' => $this->csrfToken(),
            'isDevelopment' => $this->isDevelopmentEnvironment(),
        ]);
    }

    public function registerAction()
    {
        if (!$this->isDevelopmentEnvironment()) {
            $this->flashSession->error('Cadastro disponivel somente no ambiente de desenvolvimento.');
            return $this->response->redirect('/login');
        }

        if ($this->session->has('auth')) {
            return $this->response->redirect('/dashboard');
        }

        $this->view->setVars([
            'csrfToken' => $this->csrfToken(),
            'defaultDomain' => $this->request->getQuery('domain', 'string', 'dev.local'),
        ]);
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

        if ($this->isLoginBlocked($email)) {
            $this->flashSession->error('Muitas tentativas de login. Aguarde alguns minutos e tente novamente.');
            return $this->response->redirect('/login');
        }

        $user = User::findFirst([
            'conditions' => 'email = :email: AND is_active = 1 AND deleted_at IS NULL',
            'bind' => ['email' => $email],
        ]);

        if (!$user instanceof User || !password_verify($password, $user->password)) {
            $this->registerLoginFailure($email);
            $this->flashSession->error('E-mail ou senha inválidos.');
            return $this->response->redirect('/login');
        }

        $company = $user->company;
        if (!$company instanceof Company || $company->deleted_at !== null) {
            $this->flashSession->error('A empresa vinculada a este usuário está inativa.');
            return $this->response->redirect('/login');
        }

        $this->clearLoginFailures($email);
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

        // Primeiro login (ou nova versão dos Termos): exige o aceite antes de usar.
        if (empty($user->terms_accepted_at) || $user->terms_version !== self::CURRENT_TERMS_VERSION) {
            return $this->response->redirect('/termos/aceite');
        }

        return $this->response->redirect('/dashboard');
    }

    public function createDevelopmentAccountAction()
    {
        if (!$this->isDevelopmentEnvironment()) {
            $this->flashSession->error('Cadastro disponivel somente no ambiente de desenvolvimento.');
            return $this->response->redirect('/login');
        }

        try {
            if (!$this->request->isPost()) {
                return $this->response->redirect('/register');
            }

            if (!$this->hasValidCsrfToken()) {
                throw new \RuntimeException('A sessao do formulario expirou. Tente novamente.');
            }

            $companyName = trim((string)$this->request->getPost('company_name'));
            $cnpj = $this->cnpjDigits((string)$this->request->getPost('cnpj'));
            $domain = $this->normalizeDomain((string)$this->request->getPost('domain'));
            $name = trim((string)$this->request->getPost('name'));
            $email = strtolower(trim((string)$this->request->getPost('email')));
            $cpf = $this->cpfDigits((string)$this->request->getPost('cpf'));
            $password = (string)$this->request->getPost('password');
            $passwordConfirmation = (string)$this->request->getPost('password_confirmation');

            $this->validateDevelopmentRegistration(
                $companyName,
                $cnpj,
                $domain,
                $name,
                $email,
                $cpf,
                $password,
                $passwordConfirmation
            );

            $company = new Company();
            $company->assign([
                'name' => $companyName,
                'cnpj' => $cnpj,
                'domain' => $domain,
                'contact_name' => $name,
                'contact_email' => $email,
                'contact_whatsapp' => '(00) 00000-0000',
                'admin_recovery_email' => $email,
                'secondary_recovery_email' => $this->secondaryRecoveryEmail($email, $domain),
            ]);

            $this->db->begin();

            if (!$company->create()) {
                throw new \RuntimeException('Nao foi possivel criar a empresa de desenvolvimento.');
            }

            $user = new User();
            $user->assign([
                'company_id' => (int)$company->id,
                'name' => $name,
                'email' => $email,
                'cpf' => $cpf,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'permissions' => json_encode([], JSON_UNESCAPED_UNICODE),
                'is_active' => 1,
            ]);

            if (!$user->create()) {
                throw new \RuntimeException('Nao foi possivel criar o usuario administrador.');
            }

            $this->db->commit();
            $this->refreshCsrfToken();
            $this->flashSession->success('Ambiente criado. Agora entre com o e-mail e senha cadastrados.');

            return $this->response->redirect('/login');
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }

            $this->logError('auth.developmentRegister', $e);
            $this->flashSession->error($e->getMessage());

            return $this->response->redirect('/register');
        }
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

    /** Chaves do rate limit → limite de falhas por janela (por IP e por e-mail). */
    private function loginThrottleLimits(string $email): array
    {
        $ip = (string) $this->request->getClientAddress(true);
        $ip = $ip !== '' ? $ip : 'desconhecido';

        return [
            'login:fail:ip:' . $ip => self::LOGIN_MAX_PER_IP,
            'login:fail:email:' . strtolower($email) => self::LOGIN_MAX_PER_EMAIL,
        ];
    }

    private function isLoginBlocked(string $email): bool
    {
        try {
            $redis = $this->getDI()->getShared('redis');
            foreach ($this->loginThrottleLimits($email) as $key => $max) {
                if ((int) $redis->get($key) >= $max) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            $this->logError('auth.throttle.check', $e);

            // Em producao, falha no controle anti-forca-bruta deve ser segura.
            // Em dev/local, mantem fail-open para nao travar setup sem Redis.
            return $this->isProductionEnvironment();
        }

        return false;
    }

    private function registerLoginFailure(string $email): void
    {
        try {
            $redis = $this->getDI()->getShared('redis');
            foreach (array_keys($this->loginThrottleLimits($email)) as $key) {
                if ((int) $redis->incr($key) === 1) {
                    $redis->expire($key, self::LOGIN_WINDOW_SECONDS);
                }
            }
        } catch (Throwable $e) {
            $this->logError('auth.throttle.register', $e);
        }
    }

    private function clearLoginFailures(string $email): void
    {
        try {
            $redis = $this->getDI()->getShared('redis');
            $redis->del(array_keys($this->loginThrottleLimits($email)));
        } catch (Throwable $e) {
            $this->logError('auth.throttle.clear', $e);
        }
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

    private function isProductionEnvironment(): bool
    {
        return strtolower((string)(getenv('APP_ENV') ?: '')) === 'production';
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? $domain;
        $domain = preg_replace('/\/.*$/', '', $domain) ?? $domain;
        $domain = ltrim($domain, '@');

        return $domain;
    }

    private function validateDevelopmentRegistration(
        string $companyName,
        string $cnpj,
        string $domain,
        string $name,
        string $email,
        string $cpf,
        string $password,
        string $passwordConfirmation
    ): void {
        if (strlen($companyName) < 3) {
            throw new \RuntimeException('Informe o nome do workspace.');
        }

        if (!$this->isValidCnpj($cnpj)) {
            throw new \RuntimeException('Informe um CNPJ válido para a empresa.');
        }

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            throw new \RuntimeException('Informe um dominio valido para o workspace.');
        }

        if (strlen($name) < 3) {
            throw new \RuntimeException('Informe o nome do administrador.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Informe um e-mail valido.');
        }

        if (!$this->isValidCpf($cpf)) {
            throw new \RuntimeException('Informe um CPF válido para o administrador.');
        }

        $emailDomain = Company::extractDomain($email);
        if ($emailDomain !== $domain) {
            throw new \RuntimeException("O e-mail do administrador deve usar o dominio @{$domain}.");
        }

        if (strlen($password) < 8) {
            throw new \RuntimeException('A senha deve ter pelo menos 8 caracteres.');
        }

        if ($password !== $passwordConfirmation) {
            throw new \RuntimeException('A confirmacao de senha nao confere.');
        }

        $existingUser = User::findFirst([
            'conditions' => 'email = :email: AND deleted_at IS NULL',
            'bind' => ['email' => $email],
        ]);

        if ($existingUser instanceof User) {
            throw new \RuntimeException('Ja existe um usuario ativo com este e-mail.');
        }

        $existingCpf = User::findFirst([
            'conditions' => 'cpf = :cpf:',
            'bind' => ['cpf' => $cpf],
        ]);

        if ($existingCpf instanceof User) {
            throw new \RuntimeException('Ja existe um usuario cadastrado com este CPF.');
        }

        $existingCnpj = Company::findFirst([
            'conditions' => 'cnpj = :cnpj: AND deleted_at IS NULL',
            'bind' => ['cnpj' => $cnpj],
        ]);

        if ($existingCnpj instanceof Company) {
            throw new \RuntimeException('Ja existe uma empresa ativa com este CNPJ.');
        }

        $existingAdminEmail = Company::findFirst([
            'conditions' => 'admin_recovery_email = :email: AND deleted_at IS NULL',
            'bind' => ['email' => $email],
        ]);

        if ($existingAdminEmail instanceof Company) {
            throw new \RuntimeException('Este e-mail de administrador ja esta vinculado a outro CNPJ.');
        }

        $existingCompany = Company::findFirst([
            'conditions' => 'domain = :domain: AND deleted_at IS NULL',
            'bind' => ['domain' => $domain],
        ]);

        if ($existingCompany instanceof Company) {
            throw new \RuntimeException('Ja existe uma empresa ativa com este dominio.');
        }
    }

    private function developmentCompanyCode(): string
    {
        return 'DEV-' . date('ymdHis') . '-' . random_int(10, 99);
    }

    private function cnpjDigits(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    private function cpfDigits(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf) ?? '';
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        for ($digit = 12; $digit < 14; $digit++) {
            $sum = 0;
            foreach ($weights[$digit - 12] as $index => $weight) {
                $sum += (int)$cnpj[$index] * $weight;
            }

            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int)$cnpj[$digit] !== $expected) {
                return false;
            }
        }

        return true;
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

    private function secondaryRecoveryEmail(string $adminEmail, string $domain): string
    {
        $email = 'suporte@' . $domain;

        if ($email === $adminEmail) {
            return 'backup@' . $domain;
        }

        return $email;
    }
}
