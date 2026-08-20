<?php

declare(strict_types=1);

$failures = [];
$warnings = [];

$env = static fn (string $key): string => trim((string)getenv($key));

$addFailure = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

$isPlaceholder = static function (string $value): bool {
    if ($value === '') {
        return true;
    }

    return (bool)preg_match(
        '/troque|a_preencher|a preencher|seudominio|seu_projeto|dev\.local|localhost|127\.0\.0\.1/i',
        $value
    );
};

$strongSecret = static function (string $value): bool {
    return strlen($value) >= 12
        && preg_match('/[A-Z]/', $value)
        && preg_match('/[a-z]/', $value)
        && preg_match('/\d/', $value);
};

$cnpjDigits = static fn (string $value): string => preg_replace('/\D/', '', $value) ?? '';

$isValidCnpj = static function (string $value) use ($cnpjDigits): bool {
    $cnpj = $cnpjDigits($value);
    if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $weights = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    for ($digit = 12; $digit < 14; $digit++) {
        $sum = 0;
        foreach ($weights[$digit - 12] as $index => $weight) {
            $sum += (int)$cnpj[$index] * $weight;
        }

        $remainder = $sum % 11;
        $expected = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int)$cnpj[$digit] !== $expected) {
            return false;
        }
    }

    return true;
};

if ($env('APP_ENV') !== 'production') {
    $addFailure('APP_ENV deve ser production.');
}

foreach (['APP_URL', 'LEGAL_APP_URL'] as $key) {
    $value = $env($key);
    if ($isPlaceholder($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
        $addFailure("{$key} deve ser uma URL publica valida.");
        continue;
    }

    if (!str_starts_with($value, 'https://')) {
        $addFailure("{$key} deve usar HTTPS.");
    }
}

foreach (['DB_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'ADMIN_PASSWORD'] as $key) {
    $value = $env($key);
    if ($isPlaceholder($value) || !$strongSecret($value)) {
        $addFailure("{$key} deve ser uma senha forte, real e com pelo menos 12 caracteres, letras e numeros.");
    }
}

if (!filter_var($env('ADMIN_EMAIL'), FILTER_VALIDATE_EMAIL) || $isPlaceholder($env('ADMIN_EMAIL'))) {
    $addFailure('ADMIN_EMAIL deve ser um e-mail real e valido.');
}

if ($env('MASTER_EMAIL') !== '') {
    if (!filter_var($env('MASTER_EMAIL'), FILTER_VALIDATE_EMAIL) || $isPlaceholder($env('MASTER_EMAIL'))) {
        $addFailure('MASTER_EMAIL deve ser um e-mail real e valido quando configurado.');
    }

    if ($isPlaceholder($env('MASTER_PASSWORD')) || !$strongSecret($env('MASTER_PASSWORD'))) {
        $addFailure('MASTER_PASSWORD deve ser forte quando MASTER_EMAIL estiver configurado.');
    }
} else {
    $warnings[] = 'MASTER_EMAIL vazio: ADMIN_EMAIL sera usado/promovido como master pelas migrations.';
}

if ($isPlaceholder($env('DOMAIN')) || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $env('DOMAIN'))) {
    $addFailure('DOMAIN deve ser o dominio publico real.');
}

if ($isPlaceholder($env('APP_IMAGE')) || !preg_match('/^[a-z0-9-]+-docker\.pkg\.dev\/[^\/]+\/[^\/]+\/[^:]+:.+$/i', $env('APP_IMAGE'))) {
    $addFailure('APP_IMAGE deve apontar para uma imagem valida no Artifact Registry.');
}

foreach (['LEGAL_COMPANY_NAME', 'LEGAL_FORUM', 'LEGAL_HOSTING_REGION', 'LEGAL_UPDATED_AT'] as $key) {
    if ($isPlaceholder($env($key))) {
        $addFailure("{$key} deve ser preenchido com dado real.");
    }
}

if (!$isValidCnpj($env('LEGAL_COMPANY_CNPJ'))) {
    $addFailure('LEGAL_COMPANY_CNPJ deve ser um CNPJ real e valido.');
}

foreach (['LEGAL_CONTACT_EMAIL', 'LEGAL_PRIVACY_EMAIL', 'ACME_EMAIL'] as $key) {
    if (!filter_var($env($key), FILTER_VALIDATE_EMAIL) || $isPlaceholder($env($key))) {
        $addFailure("{$key} deve ser um e-mail valido.");
    }
}

if ($env('REDIS_HOST') === '' || $env('DB_HOST') === '') {
    $addFailure('DB_HOST e REDIS_HOST devem estar configurados.');
}

foreach ($warnings as $warning) {
    echo "[warn] {$warning}" . PHP_EOL;
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[fail] {$failure}" . PHP_EOL);
    }

    exit(1);
}

echo 'Checklist de ambiente de producao OK.' . PHP_EOL;
