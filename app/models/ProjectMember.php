<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class ProjectMember extends Model
{
    public $project_id;
    public $user_id;
    public $created_at;

    public function initialize(): void
    {
        $this->setSource('project_members');
    }
}
