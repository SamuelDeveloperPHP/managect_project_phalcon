<?php

declare(strict_types=1);

namespace App\Controllers;

final class ErrorController extends ControllerBase
{
    protected bool $requiresAuthentication = false;

    public function notFoundAction(): void
    {
        $this->response->setStatusCode(404, 'Not Found');
    }
}
