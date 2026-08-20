<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    $root . '/app',
    $root . '/bin',
    $root . '/database',
];

$files = [];
foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$failed = [];

foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $failed[$file] = implode(PHP_EOL, $output);
    }
}

if ($failed !== []) {
    foreach ($failed as $file => $message) {
        fwrite(STDERR, "[fail] {$file}" . PHP_EOL . $message . PHP_EOL);
    }

    exit(1);
}

echo 'PHP lint OK: ' . count($files) . ' arquivos verificados.' . PHP_EOL;
