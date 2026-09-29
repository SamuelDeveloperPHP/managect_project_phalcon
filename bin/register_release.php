<?php

declare(strict_types=1);

/**
 * Registra a versão efetivamente implantada. O executor é obrigatório para
 * preservar a rastreabilidade operacional; branch e commit são lidos do Git
 * quando não forem enviados explicitamente pelo pipeline.
 */

$options = getopt('', [
    'branch::', 'commit::', 'executed-by:', 'released-at::', 'commit-message::',
    'implemented::', 'fixed::', 'updated::',
]);
$branch = trim((string) ($options['branch'] ?? getenv('RELEASE_BRANCH') ?: gitValue('branch --show-current')));
$commit = strtolower(trim((string) ($options['commit'] ?? getenv('RELEASE_COMMIT') ?: gitValue('rev-parse HEAD'))));
$executedBy = trim((string) ($options['executed-by'] ?? getenv('RELEASE_EXECUTED_BY') ?: ''));
$releasedAt = trim((string) ($options['released-at'] ?? date('Y-m-d H:i:s')));

if ($branch === '' || mb_strlen($branch) > 190) {
    throw new RuntimeException('Informe uma branch válida com até 190 caracteres.');
}

if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
    throw new RuntimeException('Informe o hash completo SHA-1 do commit da versão.');
}

if ($executedBy === '' || mb_strlen($executedBy) > 190) {
    throw new RuntimeException('Informe quem executou a publicação com --executed-by.');
}

$date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $releasedAt);
if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d H:i:s') !== $releasedAt) {
    throw new RuntimeException('Informe a data no formato AAAA-MM-DD HH:MM:SS.');
}

$commitMessage = trim((string) ($options['commit-message'] ?? gitValue('log -1 --format=%s ' . $commit)));
if ($commitMessage === '') {
    $commitMessage = 'Publicação de versão registrada.';
}
if (mb_strlen($commitMessage) > 500) {
    throw new RuntimeException('A mensagem do commit pode ter no máximo 500 caracteres.');
}
$notes = [
    'implemented' => releaseNotes($options['implemented'] ?? ''),
    'fixed' => releaseNotes($options['fixed'] ?? ''),
    'updated' => releaseNotes($options['updated'] ?? ''),
];

if ($notes['implemented'] === [] && $notes['fixed'] === [] && $notes['updated'] === []) {
    $classification = classifyCommit($commitMessage);
    $notes[$classification] = [$commitMessage];
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'mysql',
        (int) (getenv('DB_PORT') ?: 3306),
        getenv('DB_DATABASE') ?: 'phalcon'
    ),
    getenv('DB_USERNAME') ?: 'phalcon',
    getenv('DB_PASSWORD') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$statement = $pdo->prepare(
    'INSERT INTO release_versions (
        branch_name, commit_sha, commit_message, implemented_notes, fixed_notes,
        updated_notes, executed_by, released_at
     ) VALUES (
        :branch_name, :commit_sha, :commit_message, :implemented_notes, :fixed_notes,
        :updated_notes, :executed_by, :released_at
     )
     ON DUPLICATE KEY UPDATE
        commit_message = VALUES(commit_message),
        implemented_notes = VALUES(implemented_notes),
        fixed_notes = VALUES(fixed_notes),
        updated_notes = VALUES(updated_notes),
        executed_by = VALUES(executed_by),
        released_at = LEAST(released_at, VALUES(released_at))'
);
$statement->execute([
    'branch_name' => $branch,
    'commit_sha' => $commit,
    'commit_message' => $commitMessage,
    'implemented_notes' => json_encode($notes['implemented'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'fixed_notes' => json_encode($notes['fixed'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'updated_notes' => json_encode($notes['updated'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'executed_by' => $executedBy,
    'released_at' => $releasedAt,
]);

echo "Versão registrada: {$branch} @ " . substr($commit, 0, 12) . PHP_EOL;

function gitValue(string $arguments): string
{
    $output = [];
    $status = 1;
    exec('git ' . $arguments, $output, $status);

    return $status === 0 ? trim(implode("\n", $output)) : '';
}

function releaseNotes(mixed $value): array
{
    $items = preg_split('/\s*\|\s*/u', trim((string) $value)) ?: [];
    $notes = [];

    foreach ($items as $item) {
        $note = trim($item);
        if ($note === '') {
            continue;
        }
        if (mb_strlen($note) > 500) {
            throw new RuntimeException('Cada nota de release pode ter no máximo 500 caracteres.');
        }
        $notes[] = $note;
    }

    if (count($notes) > 25) {
        throw new RuntimeException('Informe no máximo 25 notas por categoria.');
    }

    return $notes;
}

function classifyCommit(string $message): string
{
    $message = mb_strtolower($message);

    if (preg_match('/^(feat|feature|add|implement|adiciona|implementa)/u', $message)) {
        return 'implemented';
    }
    if (preg_match('/^(fix|bug|corrige|corrigido|resolve)/u', $message)) {
        return 'fixed';
    }

    return 'updated';
}
