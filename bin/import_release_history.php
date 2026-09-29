<?php

declare(strict_types=1);

/**
 * Importa até 50 commits da branch principal já sincronizada com o GitHub.
 * A operação é idempotente: não altera executor, data ou notas manuais de
 * versões que já tenham sido registradas no sistema.
 */

$options = getopt('', ['limit::', 'branch::']);
$limit = (int) ($options['limit'] ?? 50);
$limit = max(1, min(50, $limit));
$branch = trim((string) ($options['branch'] ?? remoteDefaultBranch()));

if (!preg_match('/^[A-Za-z0-9._\/-]{1,190}$/', $branch)) {
    throw new RuntimeException('Informe uma branch válida.');
}

$ref = 'origin/' . $branch;
$format = '%H%x1f%aI%x1f%s';
$rows = array_slice(gitLines(sprintf(
    'log --reverse --format=%s %s',
    escapeshellarg($format),
    escapeshellarg($ref)
)), 0, $limit);

if ($rows === []) {
    throw new RuntimeException("Nenhum commit encontrado em {$ref}. Execute git fetch origin --prune antes da importação.");
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

$insert = $pdo->prepare(
    'INSERT INTO release_versions (
        branch_name, commit_sha, commit_message, implemented_notes,
        fixed_notes, updated_notes, executed_by, released_at
     ) VALUES (
        :branch_name, :commit_sha, :commit_message, :implemented_notes,
        :fixed_notes, :updated_notes, :executed_by, :released_at
     )
     ON DUPLICATE KEY UPDATE
        commit_message = COALESCE(NULLIF(commit_message, ""), VALUES(commit_message)),
        implemented_notes = CASE WHEN COALESCE(JSON_LENGTH(implemented_notes), 0) = 0 THEN VALUES(implemented_notes) ELSE implemented_notes END,
        fixed_notes = CASE WHEN COALESCE(JSON_LENGTH(fixed_notes), 0) = 0 THEN VALUES(fixed_notes) ELSE fixed_notes END,
        updated_notes = CASE WHEN COALESCE(JSON_LENGTH(updated_notes), 0) = 0 THEN VALUES(updated_notes) ELSE updated_notes END'
);

$imported = 0;
$pdo->beginTransaction();
try {
    foreach ($rows as $row) {
        $fields = explode("\x1f", $row, 3);
        if (count($fields) !== 3) {
            throw new RuntimeException('O histórico Git possui uma linha inválida.');
        }

        [$sha, $releasedAt, $subject] = $fields;
        if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
            throw new RuntimeException('O histórico Git possui dados de versão inválidos.');
        }
        $releasedAt = releaseDate($releasedAt);

        $subject = mb_substr(trim($subject), 0, 500);
        $category = classifyCommit($subject);
        $notes = ['implemented' => [], 'fixed' => [], 'updated' => []];
        $notes[$category] = [$subject !== '' ? $subject : 'Commit sem descrição.'];

        $insert->execute([
            'branch_name' => $branch,
            'commit_sha' => $sha,
            'commit_message' => $subject,
            'implemented_notes' => json_encode($notes['implemented'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'fixed_notes' => json_encode($notes['fixed'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_notes' => json_encode($notes['updated'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'executed_by' => 'Histórico importado do GitHub',
            'released_at' => $releasedAt,
        ]);
        $imported++;
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Histórico importado: {$imported} commit(s) da branch {$branch}." . PHP_EOL;

function remoteDefaultBranch(): string
{
    $head = gitLines('symbolic-ref --short refs/remotes/origin/HEAD');
    $value = $head[0] ?? 'origin/main';

    return preg_replace('/^origin\//', '', $value) ?: 'main';
}

function gitLines(string $arguments): array
{
    $output = [];
    $status = 1;
    exec('git ' . $arguments, $output, $status);

    if ($status !== 0) {
        return [];
    }

    return array_values(array_filter($output, static fn (string $line): bool => $line !== ''));
}

function releaseDate(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        throw new RuntimeException('O histórico Git possui uma data de versão inválida.');
    }
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
