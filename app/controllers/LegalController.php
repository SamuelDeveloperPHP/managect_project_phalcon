<?php

declare(strict_types=1);

namespace App\Controllers;

// Páginas legais públicas: Termos de Uso e Política de Privacidade.
final class LegalController extends ControllerBase
{
    protected bool $requiresAuthentication = false;
    protected bool $requiresTermsAcceptance = false;

    public function termosAction(): void
    {
        $this->view->setVars($this->legalViewVars() + [
            'version' => self::CURRENT_TERMS_VERSION,
            'loggedIn' => $this->session->has('auth'),
        ]);
    }

    public function privacidadeAction(): void
    {
        $this->view->setVars($this->legalViewVars() + [
            'version' => self::CURRENT_TERMS_VERSION,
            'loggedIn' => $this->session->has('auth'),
        ]);
    }

    private function legalViewVars(): array
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http')) ?: 'http';
        $defaultUrl = $scheme . '://' . $host;

        return [
            'appUrl' => rtrim((string)(getenv('LEGAL_APP_URL') ?: getenv('APP_URL') ?: $defaultUrl), '/'),
            'legalCompanyName' => (string)(getenv('LEGAL_COMPANY_NAME') ?: 'NexoCore Tecnologia LTDA'),
            'legalCompanyCnpj' => (string)(getenv('LEGAL_COMPANY_CNPJ') ?: 'CNPJ a configurar'),
            'legalForum' => (string)(getenv('LEGAL_FORUM') ?: 'foro a configurar'),
            'legalContactEmail' => (string)(getenv('LEGAL_CONTACT_EMAIL') ?: 'contato@nexocoretecnologia.com.br'),
            'legalPrivacyEmail' => (string)(getenv('LEGAL_PRIVACY_EMAIL') ?: 'privacidade@nexocoretecnologia.com.br'),
            'legalHostingRegion' => (string)(getenv('LEGAL_HOSTING_REGION') ?: 'regiao configurada pelo provedor de hospedagem'),
            'legalUpdatedAt' => (string)(getenv('LEGAL_UPDATED_AT') ?: '20 de agosto de 2026'),
        ];
    }
}
