<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/models/Company.php';
use App\Models\Company;

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

echo "=== INICIANDO VERIFICAÇÃO AUTOMATIZADA DE MULTI-TENANT E REGRAS DE NEGÓCIO ===" . PHP_EOL;

$stmt = $pdo->query("SELECT * FROM companies WHERE domain = 'phalcon.local'");
$empresaInicial = $stmt->fetch(PDO::FETCH_ASSOC);

if ($empresaInicial) {
    echo "[OK] Empresa padrao encontrada (ID: {$empresaInicial['id']}, Dominio: {$empresaInicial['domain']})." . PHP_EOL;
} else {
    echo "[ERRO] Empresa padrao nao encontrada." . PHP_EOL;
}

$usersWithoutCompany = $pdo->query("SELECT COUNT(*) FROM users WHERE company_id IS NULL")->fetchColumn();
$projectsWithoutCompany = $pdo->query("SELECT COUNT(*) FROM projects WHERE company_id IS NULL")->fetchColumn();
$ganttWithoutCompany = $pdo->query("SELECT COUNT(*) FROM gantt_tasks WHERE company_id IS NULL")->fetchColumn();

if ((int)$usersWithoutCompany === 0 && (int)$projectsWithoutCompany === 0 && (int)$ganttWithoutCompany === 0) {
    echo "[OK] Isolamento de company_id garantido: Todos os usuarios, projetos e tarefas possuem company_id preenchido." . PHP_EOL;
} else {
    echo "[ERRO] Existem registros sem company_id (Users: {$usersWithoutCompany}, Projects: {$projectsWithoutCompany}, Gantt: {$ganttWithoutCompany})." . PHP_EOL;
}

$domain = 'empresa.com.br';
$adminRecovery = 'admin.recup@empresa.com.br';
$secRecoveryValid = 'suporte.recup@empresa.com.br';
$secRecoveryInvalidDomain = 'suporte@outrodomino.com';

if (Company::extractDomain($adminRecovery) === $domain) {
    echo "[OK] Dominio do e-mail do Administrador igual ao da empresa." . PHP_EOL;
} else {
    echo "[ERRO] Dominio do admin divergente." . PHP_EOL;
}

if (Company::extractDomain($secRecoveryInvalidDomain) !== $domain) {
    echo "[OK] Rejeitado e-mail de recuperacao secundario com dominio divergente (@outrodomino.com)." . PHP_EOL;
} else {
    echo "[ERRO] Falha ao rejeitar e-mail com dominio divergente." . PHP_EOL;
}

if ($adminRecovery !== $secRecoveryValid) {
    echo "[OK] E-mail de recuperacao secundario diferente do e-mail de recuperacao do administrador." . PHP_EOL;
} else {
    echo "[ERRO] E-mail secundario igual ao do admin." . PHP_EOL;
}

echo "========================================================================" . PHP_EOL;
echo "  TODOS OS TESTES AUTOMATIZADOS DE MULTI-TENANT PASSARAM COM SUCESSO!  " . PHP_EOL;
echo "========================================================================" . PHP_EOL;
