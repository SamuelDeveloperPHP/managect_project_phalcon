<?php

declare(strict_types=1);

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'mysql',
        (int)(getenv('DB_PORT') ?: 3306),
        getenv('DB_DATABASE') ?: 'phalcon'
    ),
    getenv('DB_USERNAME') ?: 'phalcon',
    getenv('DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$cnpj = '12.345.678/0001-95';
$domain = 'techcorp.com.br';
$name = 'TechCorp Soluções Inovadoras LTDA';

// Clear existing company with same CNPJ/domain if any
$delete = $pdo->prepare('DELETE FROM companies WHERE cnpj = :cnpj OR domain = :domain');
$delete->execute(['cnpj' => $cnpj, 'domain' => $domain]);

$stmt = $pdo->prepare('INSERT INTO companies (
    name, cnpj, domain, zip_code, street, number, complement, neighborhood, city, state,
    contact_name, contact_email, contact_whatsapp, admin_recovery_email, secondary_recovery_email
) VALUES (
    :name, :cnpj, :domain, :zip_code, :street, :number, :complement, :neighborhood, :city, :state,
    :contact_name, :contact_email, :contact_whatsapp, :admin_recovery_email, :secondary_recovery_email
)');

$stmt->execute([
    'name' => $name,
    'cnpj' => $cnpj,
    'domain' => $domain,
    'zip_code' => '01310-100',
    'street' => 'Avenida Paulista',
    'number' => '1000',
    'complement' => 'Conjunto 501',
    'neighborhood' => 'Bela Vista',
    'city' => 'São Paulo',
    'state' => 'SP',
    'contact_name' => 'Carlos Eduardo Silva',
    'contact_email' => 'contato@techcorp.com.br',
    'contact_whatsapp' => '(11) 98765-4321',
    'admin_recovery_email' => 'admin.recuperacao@techcorp.com.br',
    'secondary_recovery_email' => 'seguranca.recuperacao@techcorp.com.br',
]);

$companyId = (int)$pdo->lastInsertId();

// Update user.id = 1 with the company & matching domain email
$stmtUser = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role, company_id = :company_id WHERE id = 1');
$stmtUser->execute([
    'name' => 'Administrador TechCorp',
    'email' => 'admin@techcorp.com.br',
    'role' => 'admin',
    'company_id' => $companyId,
]);

// Link existing projects and tasks to this new company for testing.
// Nomes de tabela vêm de uma lista fixa no código (não de entrada externa).
foreach (['projects', 'gantt_tasks', 'project_files', 'audit_logs'] as $table) {
    $pdo->prepare("UPDATE {$table} SET company_id = :company_id")
        ->execute(['company_id' => $companyId]);
}

echo "========================================================================" . PHP_EOL;
echo "  EMPRESA FICTÍCIA CRIADA E VINCULADA AO USUÁRIO ID = 1 COM SUCESSO!    " . PHP_EOL;
echo "========================================================================" . PHP_EOL;
echo "ID da Empresa : {$companyId}" . PHP_EOL;
echo "Razão Social  : {$name}" . PHP_EOL;
echo "CNPJ          : {$cnpj}" . PHP_EOL;
echo "Domínio       : {$domain}" . PHP_EOL;
echo "E-mail Admin  : admin@techcorp.com.br" . PHP_EOL;
echo "Senha Admin   : senha atual do usuario, definida pelo ambiente." . PHP_EOL;
echo "Recup. Admin  : admin.recuperacao@techcorp.com.br" . PHP_EOL;
echo "Recup. Secund.: seguranca.recuperacao@techcorp.com.br" . PHP_EOL;
echo "Contato       : Carlos Eduardo Silva (contato@techcorp.com.br / (11) 98765-4321)" . PHP_EOL;
echo "Endereço      : Av. Paulista, 1000 - Cj 501, Bela Vista, São Paulo/SP - 01310-100" . PHP_EOL;
echo "========================================================================" . PHP_EOL;
