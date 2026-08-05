<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class AuditLog extends Model
{
    public $id;
    public $user_id;
    public $company_id;
    public $action;
    public $entity_type;
    public $entity_id;
    public $description;
    public $ip_address;
    public $user_agent;
    public $metadata;
    public $created_at;

    public function initialize(): void
    {
        $this->setSource('audit_logs');
    }
}
