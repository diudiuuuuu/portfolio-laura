<?php
declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$from = '';
$to = '';
$rewriteLocalUploads = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = trim((string) substr($arg, strlen('--from=')));
        continue;
    }
    if (str_starts_with($arg, '--to=')) {
        $to = trim((string) substr($arg, strlen('--to=')));
        continue;
    }
    if ($arg === '--rewrite-local-uploads') {
        $rewriteLocalUploads = true;
    }
}

if ($to === '') {
    fwrite(STDERR, "Missing required --to=https://your-cdn-domain\n");
    exit(1);
}

$from = rtrim($from, '/');
$to = rtrim($to, '/');

$content = loadSiteContent();
$visited = 0;
$changed = 0;
$samples = [];

$walk = function (&$node) use (&$walk, $from, $to, $rewriteLocalUploads, &$visited, &$changed, &$samples): void {
    if (is_array($node)) {
        foreach ($node as &$value) {
            $walk($value);
        }
        unset($value);
        return;
    }

    if (!is_string($node)) {
        return;
    }

    $visited++;
    $original = $node;
    $updated = $original;

    if ($from !== '' && str_starts_with($original, $from . '/')) {
        $suffix = substr($original, strlen($from));
        $updated = $to . $suffix;
    } elseif ($rewriteLocalUploads && str_starts_with($original, '/uploads/')) {
        $updated = $to . $original;
    }

    if ($updated === $original) {
        return;
    }

    $node = $updated;
    $changed++;
    if (count($samples) < 10) {
        $samples[] = $original . ' -> ' . $updated;
    }
};

$walk($content);

if ($dryRun) {
    echo "Dry run complete.\n";
    echo "Visited strings: {$visited}\n";
    echo "Would update URLs: {$changed}\n";
    if ($samples !== []) {
        echo "Sample replacements:\n";
        foreach ($samples as $sample) {
            echo " - {$sample}\n";
        }
    }
    exit(0);
}

saveSiteContent($content);
echo "Rewrite complete.\n";
echo "Visited strings: {$visited}\n";
echo "Updated URLs: {$changed}\n";
if ($samples !== []) {
    echo "Sample replacements:\n";
    foreach ($samples as $sample) {
        echo " - {$sample}\n";
    }
}
