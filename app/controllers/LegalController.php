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
        $this->view->setVars([
            'version' => self::CURRENT_TERMS_VERSION,
            'loggedIn' => $this->session->has('auth'),
        ]);
    }

    public function privacidadeAction(): void
    {
        $this->view->setVars([
            'version' => self::CURRENT_TERMS_VERSION,
            'loggedIn' => $this->session->has('auth'),
        ]);
    }
}
