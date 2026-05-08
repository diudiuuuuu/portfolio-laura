<?php
declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$syncBlob = in_array('--sync-blob', $argv, true);
$updateData = !in_array('--no-update-data', $argv, true);
$blobEnabled = blobUploadEnabled();

if ($syncBlob && !$blobEnabled) {
    fwrite(STDERR, "BLOB_READ_WRITE_TOKEN is missing, fallback to local-only mode.\n");
    $syncBlob = false;
}

$uploadsBase = rtrim(UPLOAD_BASE, '/');
if (!is_dir($uploadsBase)) {
    fwrite(STDERR, "Uploads directory not found: {$uploadsBase}\n");
    exit(1);
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
$replaceMap = [];
$scanned = 0;
$optimized = 0;
$alreadyOptimized = 0;
$blobSynced = 0;
$errors = 0;
$errorSamples = [];

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsBase, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($rii as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $abs = $file->getPathname();
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        continue;
    }
    if (isGeneratedWebpVariantPath($abs)) {
        continue;
    }

    $scanned++;
    $publicPath = '/uploads/' . ltrim(substr($abs, strlen($uploadsBase)), '/');
    $base = preg_replace('/\.[^.]+$/', '', $abs);
    if (!is_string($base) || $base === '') {
        $errors++;
        if (count($errorSamples) < 8) {
            $errorSamples[] = $publicPath . ' (invalid base path)';
        }
        continue;
    }
    $hasVariants = is_file($base . '_sm.' . $ext) && is_file($base . '_md.' . $ext) && is_file($base . '_lg.' . $ext);
    $targetPublicPath = $publicPath;

    if ($hasVariants) {
        $alreadyOptimized++;
    } else {
        if (!$dryRun) {
            $result = optimizeImageAndVariants($abs);
            if (!is_string($result) || !is_file($result)) {
                $errors++;
                if (count($errorSamples) < 8) {
                    $errorSamples[] = $publicPath . ' (optimize failed)';
                }
                continue;
            }
        }
        $optimized++;
    }

    if ($updateData) {
        $replaceMap[$publicPath] = $targetPublicPath;
    }

    if ($syncBlob) {
        if (!$dryRun) {
            $blobUrl = syncPublicUploadPathToBlob($targetPublicPath);
            if ($blobUrl === $targetPublicPath) {
                $errors++;
                if (count($errorSamples) < 8) {
                    $errorSamples[] = $targetPublicPath . ' (blob sync failed)';
                }
                continue;
            }
            $replaceMap[$publicPath] = $blobUrl;
            $replaceMap[$targetPublicPath] = $blobUrl;
        }
        $blobSynced++;
    }
}

if ($updateData) {
    $content = loadSiteContent();
    $walk = function (&$node) use (&$walk, $replaceMap): void {
        if (is_array($node)) {
            foreach ($node as &$child) {
                $walk($child);
            }
            unset($child);
            return;
        }
        if (!is_string($node)) {
            return;
        }
        if (isset($replaceMap[$node])) {
            $node = $replaceMap[$node];
        }
    };
    $walk($content);
    if (!$dryRun) {
        saveSiteContent($content);
    }
}

echo $dryRun ? "Dry run complete.\n" : "Backfill complete.\n";
echo "Scanned images: {$scanned}\n";
echo "Newly optimized: {$optimized}\n";
echo "Already optimized: {$alreadyOptimized}\n";
echo "Blob sync attempts: {$blobSynced}\n";
echo "Replacement map size: " . count($replaceMap) . "\n";
echo "Errors: {$errors}\n";
if ($errorSamples !== []) {
    echo "Error samples:\n";
    foreach ($errorSamples as $line) {
        echo " - {$line}\n";
    }
}
