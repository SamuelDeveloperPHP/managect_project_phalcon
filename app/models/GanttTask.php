<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class GanttTask extends Model
{
    public $id;
    public $project_id;
    public $company_id;
    public $code;
    public $name;
    public $description;
    public $level;
    public $status;
    public $progress;
    public $start_at;
    public $end_at;
    public $duration;
    public $depends;
    public $sort_order;
    public $collapsed;
    public $start_is_milestone;
    public $end_is_milestone;
    public $created_by;
    public $updated_by;
    public $created_at;
    public $updated_at;

    public function initialize(): void
    {
        $this->setSource('gantt_tasks');
    }
}
