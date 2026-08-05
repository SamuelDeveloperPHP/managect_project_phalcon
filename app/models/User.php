<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class User extends Model
{
    public $id;
    public $company_id;
    public $name;
    public $email;
    public $password;
    public $role;
    public $permissions;
    public $is_active;
    public $last_login_at;
    public $last_seen_at;
    public $deleted_at;
    public $created_at;
    public $updated_at;

    public function initialize(): void
    {
        $this->setSource('users');
        $this->belongsTo('company_id', Company::class, 'id', ['alias' => 'company']);
    }

    public function getPermissionsArray(): array
    {
        if (empty($this->permissions)) {
            return [];
        }

        $decoded = json_decode((string) $this->permissions, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->role === 'admin' || $this->role === 'master') {
            return true;
        }

        $perms = $this->getPermissionsArray();
        return !empty($perms[$permissionKey]);
    }
}
