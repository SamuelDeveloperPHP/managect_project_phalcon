<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class ProjectFile extends Model
{
    public $id;
    public $project_id;
    public $company_id;
    public $original_name;
    public $stored_name;
    public $file_path;
    public $mime_type;
    public $file_size;
    public $uploaded_by;
    public $created_at;

    public function initialize(): void
    {
        $this->setSource('project_files');
    }
}
