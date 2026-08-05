<?php
declare(strict_types=1);
namespace App\Controllers;
final class UsersController extends ControllerBase {
    protected bool $requiresAdmin=true;
    public function indexAction(): void {
        $this->view->setVars(['csrfToken'=>$this->csrfToken(),'auth'=>$this->session->get('auth'),'pageTitle'=>'Gestão de usuários','showNavigation'=>true]);
        $this->view->pick('dashboard/users');
    }
}
