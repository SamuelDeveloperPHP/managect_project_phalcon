<?php

declare(strict_types=1);

namespace App\Models;

use Phalcon\Mvc\Model;

final class Company extends Model
{
    public $id;
    public $name;
    public $cnpj;
    public $domain;
    public $zip_code;
    public $street;
    public $number;
    public $complement;
    public $neighborhood;
    public $city;
    public $state;
    public $logo_path;
    public $contact_name;
    public $contact_email;
    public $contact_whatsapp;
    public $admin_recovery_email;
    public $secondary_recovery_email;
    public $deleted_at;
    public $created_at;
    public $updated_at;

    public function initialize(): void
    {
        $this->setSource('companies');
    }

    public static function extractDomain(string $email): string
    {
        $parts = explode('@', strtolower(trim($email)));
        return count($parts) === 2 ? trim($parts[1]) : '';
    }

    public function isValidCompanyDomain(string $email): bool
    {
        $emailDomain = self::extractDomain($email);
        $companyDomain = strtolower(trim($this->domain ?? ''));
        return $emailDomain !== '' && $emailDomain === $companyDomain;
    }
}
