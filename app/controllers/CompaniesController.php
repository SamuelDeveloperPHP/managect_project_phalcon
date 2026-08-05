<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Company;
use App\Models\User;
use Throwable;

final class CompaniesController extends ControllerBase
{
    public function profileAction(): void
    {
        $companyId = $this->currentCompanyId();
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

            $companyId = $this->currentCompanyId();
            $company = Company::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $companyId],
            ]);

            if (!$company instanceof Company) {
                throw new \RuntimeException('Empresa não encontrada.');
            }

            $name = trim((string)$this->request->getPost('name'));
            $cnpj = trim((string)$this->request->getPost('cnpj'));
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

            // Handle Logo Upload
            if ($this->request->hasFiles()) {
                foreach ($this->request->getUploadedFiles() as $file) {
                    if ($file->getKey() === 'logo' && $file->getSize() > 0) {
                        $ext = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
                        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                            throw new \RuntimeException('Formato de imagem inválido para a logo. Use PNG, JPG, SVG ou WEBP.');
                        }

                        $uploadDir = BASE_PATH . '/public/uploads/logos';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $filename = 'logo_' . $companyId . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $filename;
                        if ($file->moveTo($targetPath)) {
                            $company->logo_path = '/uploads/logos/' . $filename;
                        }
                    }
                }
            }

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

        return $this->response->redirect('/companies/profile');
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
