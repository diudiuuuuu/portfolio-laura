<?php
declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI only.\n");
    exit(1);
}

$content = loadSiteContent();
$issues = [];

$walk = function ($node, string $path = '$') use (&$walk, &$issues): void {
    if (is_array($node)) {
        foreach ($node as $key => $value) {
            $childPath = $path . '[' . (is_int($key) ? $key : var_export((string) $key, true)) . ']';
            $walk($value, $childPath);
        }
        return;
    }

    if (!is_string($node)) {
        return;
    }

    if (str_starts_with($node, '/uploads/')) {
        $issues[] = $path . ' => ' . $node;
    }
};

$walk($content);

if ($issues === []) {
    echo "OK: all media references point to remote storage.\n";
    exit(0);
}

fwrite(STDERR, "Found local upload references that will not deploy correctly:\n");
foreach ($issues as $issue) {
    fwrite(STDERR, " - " . $issue . "\n");
}
fwrite(STDERR, "Fix these entries before deploy.\n");
exit(2);
