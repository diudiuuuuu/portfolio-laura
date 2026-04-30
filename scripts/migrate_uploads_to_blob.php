<?php
declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$token = trim((string) getenv('BLOB_READ_WRITE_TOKEN'));
if ($token === '') {
    fwrite(STDERR, "Missing BLOB_READ_WRITE_TOKEN.\n");
    exit(1);
}

$content = loadSiteContent();
$uploadedCache = [];
$changed = 0;
$failed = 0;
$visited = 0;

$walk = function (&$node) use (&$walk, &$uploadedCache, &$changed, &$failed, &$visited): void {
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
    if (!str_starts_with($node, '/uploads/')) {
        return;
    }

    if (isset($uploadedCache[$node])) {
        if (is_string($uploadedCache[$node]) && $uploadedCache[$node] !== '') {
            $node = $uploadedCache[$node];
            $changed++;
        }
        return;
    }

    $abs = absolutePathFromPublicUploadPath($node);
    if ($abs === null) {
        $uploadedCache[$node] = '';
        $failed++;
        return;
    }

    $blobPath = ltrim($node, '/');
    $url = uploadFileToBlob($abs, $blobPath, guessContentTypeFromExtension($node));
    if (!is_string($url) || $url === '') {
        $uploadedCache[$node] = '';
        $failed++;
        return;
    }

    $uploadedCache[$node] = $url;
    $node = $url;
    $changed++;
};

$walk($content);

if ($dryRun) {
    echo "Dry run complete.\n";
    echo "Visited strings: {$visited}\n";
    echo "Would update paths: {$changed}\n";
    echo "Upload failures: {$failed}\n";
    exit(0);
}

saveSiteContent($content);
echo "Migration complete.\n";
echo "Visited strings: {$visited}\n";
echo "Updated paths: {$changed}\n";
echo "Upload failures: {$failed}\n";
