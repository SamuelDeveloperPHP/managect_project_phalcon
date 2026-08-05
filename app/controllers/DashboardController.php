<?php

declare(strict_types=1);

namespace App\Controllers;

final class DashboardController extends ControllerBase
{
    public function indexAction(): void
    {
        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Dashboard',
        ]);
    }
}
