<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class Project extends Model
{
    public $id;
    public $company_id;
    public $name;
    public $code;
    public $client;
    public $description;
    public $status;
    public $priority;
    public $leader_id;
    public $start_date;
    public $deadline;
    public $budget;
    public $image_path;
    public $created_by;
    public $updated_by;
    public $deleted_at;
    public $created_at;
    public $updated_at;

    public function initialize(): void
    {
        $this->setSource('projects');
        $this->belongsTo('company_id', Company::class, 'id', ['alias' => 'company']);
    }
}
