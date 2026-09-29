<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class ReleaseVersion extends Model
{
    public $id;
    public $branch_name;
    public $commit_sha;
    public $commit_message;
    public $implemented_notes;
    public $fixed_notes;
    public $updated_notes;
    public $executed_by;
    public $released_at;
    public $created_at;

    public function initialize(): void
    {
        $this->setSource('release_versions');
    }
}
