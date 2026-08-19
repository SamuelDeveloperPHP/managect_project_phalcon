<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;
use Phalcon\Http\Request\FileInterface;
use Throwable;

final class CompaniesController extends ControllerBase
{
    private const ALLOWED_LOGO_MIMES = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    private const ALLOWED_LOGO_EXTENSIONS = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    private const MAX_LOGO_BYTES = 2097152; // 2 MB

    public function profileAction(): void
    {
        $companyId = $this->requestedCompanyId();
        $company = Company::findFirst([
            'conditions' => 'id = :id: AND deleted_at IS NULL',
            'bind' => ['id' => $companyId],
        ]);

        if (!$company instanceof Company) {
            $this->flashSession->error('Empresa não encontrada.');
            $this->response->redirect('/dashboard');
            return;
        }

        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Perfil da Empresa',
            'company' => $company,
            'companies' => $this->isMasterUser() ? $this->companies() : [],
            'selectedCompanyId' => $companyId,
        ]);
    }

    public function updateProfileAction()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->response->redirect('/companies/profile');
            }

            if (!$this->hasValidCsrfToken()) {
                throw new \RuntimeException('Token de segurança inválido.');
            }

            $user = $this->session->get('auth');
            if (($user['role'] ?? '') !== 'admin' && ($user['role'] ?? '') !== 'master') {
                throw new \RuntimeException('Somente o Administrador da empresa pode atualizar as configurações.');
            }

            $companyId = $this->requestedCompanyId(true);
            $company = Company::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $companyId],
            ]);

            if (!$company instanceof Company) {
                throw new \RuntimeException('Empresa não encontrada.');
            }

            $name = trim((string)$this->request->getPost('name'));
            $cnpj = $this->cnpjDigits((string)$this->request->getPost('cnpj'));
            $domain = strtolower(trim((string)$this->request->getPost('domain')));

            $contactName = trim((string)$this->request->getPost('contact_name'));
            $contactEmail = strtolower(trim((string)$this->request->getPost('contact_email')));
            $contactWhatsapp = trim((string)$this->request->getPost('contact_whatsapp'));

            $adminRecoveryEmail = strtolower(trim((string)$this->request->getPost('admin_recovery_email')));
            $secondaryRecoveryEmail = strtolower(trim((string)$this->request->getPost('secondary_recovery_email')));

            $zipCode = trim((string)$this->request->getPost('zip_code'));
            $street = trim((string)$this->request->getPost('street'));
            $number = trim((string)$this->request->getPost('number'));
            $complement = trim((string)$this->request->getPost('complement'));
            $neighborhood = trim((string)$this->request->getPost('neighborhood'));
            $city = trim((string)$this->request->getPost('city'));
            $state = strtoupper(trim((string)$this->request->getPost('state')));

            if ($name === '' || $cnpj === '' || $domain === '') {
                throw new \RuntimeException('Nome, CNPJ e Domínio da empresa são obrigatórios.');
            }

            if (!$this->isValidCnpj($cnpj)) {
                throw new \RuntimeException('Informe um CNPJ válido.');
            }

            if ($contactName === '' || $contactEmail === '' || $contactWhatsapp === '') {
                throw new \RuntimeException('Todos os dados de contato (Nome, E-mail e WhatsApp) são obrigatórios.');
            }

            if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Informe um e-mail de contato válido.');
            }

            // Domain matching validations for recovery emails
            if (!filter_var($adminRecoveryEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('E-mail de recuperação do administrador é inválido.');
            }

            if (!filter_var($secondaryRecoveryEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('E-mail de recuperação secundário é inválido.');
            }

            // Secondary recovery email cannot be equal to admin recovery email
            if ($adminRecoveryEmail === $secondaryRecoveryEmail) {
                throw new \RuntimeException('O e-mail de recuperação secundário NÃO pode ser igual ao e-mail de recuperação do administrador.');
            }

            // Check domain match for both recovery emails
            $adminRecoveryDomain = Company::extractDomain($adminRecoveryEmail);
            $secondaryRecoveryDomain = Company::extractDomain($secondaryRecoveryEmail);

            if ($adminRecoveryDomain !== $domain) {
                throw new \RuntimeException("O e-mail de recuperação do administrador deve possuir o mesmo domínio da empresa (@{$domain}).");
            }

            if ($secondaryRecoveryDomain !== $domain) {
                throw new \RuntimeException("O e-mail de recuperação secundário deve possuir o mesmo domínio da empresa (@{$domain}).");
            }

            $this->ensureUniqueCompanyData((int)$company->id, $cnpj, $domain, $adminRecoveryEmail);
            $this->saveLogoUpload($company, $companyId);

            $company->name = $name;
            $company->cnpj = $cnpj;
            $company->domain = $domain;
            $company->contact_name = $contactName;
            $company->contact_email = $contactEmail;
            $company->contact_whatsapp = $contactWhatsapp;
            $company->admin_recovery_email = $adminRecoveryEmail;
            $company->secondary_recovery_email = $secondaryRecoveryEmail;
            $company->zip_code = $zipCode;
            $company->street = $street;
            $company->number = $number;
            $company->complement = $complement;
            $company->neighborhood = $neighborhood;
            $company->city = $city;
            $company->state = $state;

            $this->db->begin();
            if (!$company->save()) {
                throw new \RuntimeException('Não foi possível salvar as informações da empresa.');
            }

            $this->audit('company_updated', 'companies', (int)$company->id, 'Perfil da empresa atualizado', [
                'name' => $company->name,
                'domain' => $company->domain,
            ]);

            $this->db->commit();
            $this->flashSession->success('Dados da empresa atualizados com sucesso.');
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            $this->logError('company.updateProfile', $e);
            $this->flashSession->error($e->getMessage());
        }

        $suffix = $this->isMasterUser() && isset($companyId) && (int)$companyId > 0
            ? '?company_id=' . (int)$companyId
            : '';

        return $this->response->redirect('/companies/profile' . $suffix);
    }

    private function requestedCompanyId(bool $fromPost = false): int
    {
        if (!$this->isMasterUser()) {
            return $this->currentCompanyId();
        }

        $companyId = $fromPost
            ? (int)$this->request->getPost('company_id')
            : (int)$this->request->getQuery('company_id');

        if ($companyId <= 0) {
            $companyId = $this->currentCompanyId();
        }

        $this->requireCompanyAccess($companyId);

        return $companyId;
    }

    private function companies(): array
    {
        $rows = Company::find([
            'conditions' => 'deleted_at IS NULL',
            'order' => 'name ASC',
        ]);

        return iterator_to_array($rows);
    }

    private function ensureUniqueCompanyData(int $companyId, string $cnpj, string $domain, string $adminEmail): void
    {
        $existingCnpj = Company::findFirst([
            'conditions' => 'cnpj = :cnpj: AND id <> :id: AND deleted_at IS NULL',
            'bind' => ['cnpj' => $cnpj, 'id' => $companyId],
        ]);
        if ($existingCnpj instanceof Company) {
            throw new \RuntimeException('Já existe uma empresa ativa cadastrada com este CNPJ.');
        }

        $existingDomain = Company::findFirst([
            'conditions' => 'domain = :domain: AND id <> :id: AND deleted_at IS NULL',
            'bind' => ['domain' => $domain, 'id' => $companyId],
        ]);
        if ($existingDomain instanceof Company) {
            throw new \RuntimeException('Já existe uma empresa ativa cadastrada com este domínio.');
        }

        $existingAdmin = Company::findFirst([
            'conditions' => 'admin_recovery_email = :email: AND id <> :id: AND deleted_at IS NULL',
            'bind' => ['email' => $adminEmail, 'id' => $companyId],
        ]);
        if ($existingAdmin instanceof Company) {
            throw new \RuntimeException('Este e-mail de administrador já está vinculado a outro CNPJ.');
        }
    }

    private function cnpjDigits(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
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

    private function saveLogoUpload(Company $company, int $companyId): void
    {
        if (!$this->request->hasFiles(true)) {
            return;
        }

        foreach ($this->request->getUploadedFiles(true) as $file) {
            if (!$file instanceof FileInterface || $file->getKey() !== 'logo' || (int)$file->getSize() <= 0) {
                continue;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Não foi possível receber o arquivo da logo.');
            }

            if ((int)$file->getSize() > self::MAX_LOGO_BYTES) {
                throw new \RuntimeException('A logo excede o limite de 2 MB.');
            }

            $extension = strtolower(pathinfo((string)$file->getName(), PATHINFO_EXTENSION));
            if (!isset(self::ALLOWED_LOGO_EXTENSIONS[$extension])) {
                throw new \RuntimeException('Formato de imagem inválido para a logo. Use PNG, JPG ou WEBP.');
            }

            $detectedMime = $this->detectMime((string)$file->getTempName());
            if ($detectedMime === '' || !in_array($detectedMime, self::ALLOWED_LOGO_MIMES, true)) {
                throw new \RuntimeException('Conteúdo de imagem inválido para a logo.');
            }

            if (!in_array($detectedMime, self::ALLOWED_LOGO_EXTENSIONS[$extension], true)) {
                throw new \RuntimeException('A extensão da logo não corresponde ao conteúdo do arquivo.');
            }

            $uploadDir = dirname(__DIR__, 2) . '/public/uploads/logos';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('Não foi possível preparar a pasta de logos.');
            }

            $filename = 'logo_' . $companyId . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
            $targetPath = $uploadDir . '/' . $filename;
            if (!$file->moveTo($targetPath)) {
                throw new \RuntimeException('Não foi possível salvar a logo enviada.');
            }

            $company->logo_path = '/uploads/logos/' . $filename;
        }
    }

    private function detectMime(string $path): string
    {
        if ($path === '' || !is_readable($path) || !function_exists('finfo_open')) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = (string)finfo_file($finfo, $path);
        finfo_close($finfo);

        return strtolower($mime);
    }

    public function cnpjLookupAction()
    {
        $this->view->disable();
        try {
            $cnpj = preg_replace('/\D/', '', (string)$this->request->getQuery('cnpj'));
            if (strlen($cnpj) !== 14) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'CNPJ inválido. Informe 14 dígitos.',
                ]);
            }

            if (!$this->isValidCnpj($cnpj)) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'CNPJ inválido.',
                ]);
            }

            $currentCompanyId = $this->requestedCompanyId();
            $existing = Company::findFirst([
                'conditions' => 'cnpj = :cnpj: AND id <> :id: AND deleted_at IS NULL',
                'bind' => ['cnpj' => $cnpj, 'id' => $currentCompanyId],
            ]);
            if ($existing instanceof Company) {
                return $this->response->setStatusCode(409)->setJsonContent([
                    'success' => false,
                    'message' => 'Este CNPJ já está cadastrado em outra empresa.',
                ]);
            }

            $url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $raw = @file_get_contents($url, false, $ctx);

            if ($raw === false) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'Não foi possível consultar o CNPJ na API pública no momento.',
                ]);
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || isset($data['message'])) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => $data['message'] ?? 'CNPJ não encontrado.',
                ]);
            }

            return $this->response->setJsonContent([
                'success' => true,
                'data' => [
                    'name' => $data['razao_social'] ?? $data['nome_fantasia'] ?? '',
                    'zip_code' => $data['cep'] ?? '',
                    'street' => trim(($data['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($data['logradouro'] ?? '')),
                    'number' => $data['numero'] ?? '',
                    'complement' => $data['complemento'] ?? '',
                    'neighborhood' => $data['bairro'] ?? '',
                    'city' => $data['municipio'] ?? '',
                    'state' => $data['uf'] ?? '',
                    'email' => $data['email'] ?? '',
                    'phone' => $data['ddd_telefone_1'] ?? '',
                ],
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(422)->setJsonContent([
                'success' => false,
                'message' => 'Erro na busca de CNPJ: ' . $e->getMessage(),
            ]);
        }
    }

    public function cepLookupAction()
    {
        $this->view->disable();
        try {
            $cep = preg_replace('/\D/', '', (string)$this->request->getQuery('cep'));
            if (strlen($cep) !== 8) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'CEP inválido. Informe 8 dígitos.',
                ]);
            }

            $url = "https://viacep.com.br/ws/{$cep}/json/";
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $raw = @file_get_contents($url, false, $ctx);

            if ($raw === false) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'Não foi possível consultar o CEP no ViaCEP.',
                ]);
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || !empty($data['erro'])) {
                return $this->response->setStatusCode(422)->setJsonContent([
                    'success' => false,
                    'message' => 'CEP não encontrado.',
                ]);
            }

            return $this->response->setJsonContent([
                'success' => true,
                'data' => [
                    'street' => $data['logradouro'] ?? '',
                    'neighborhood' => $data['bairro'] ?? '',
                    'city' => $data['localidade'] ?? '',
                    'state' => $data['uf'] ?? '',
                ],
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(422)->setJsonContent([
                'success' => false,
                'message' => 'Erro na busca de CEP.',
            ]);
        }
    }
}
