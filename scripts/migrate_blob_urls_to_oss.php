<?php
declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI only.\n");
    exit(1);
}

@set_time_limit(0);

function envRequired(string $name): string
{
    $value = trim((string) getenv($name));
    if ($value === '') {
        fwrite(STDERR, "Missing {$name}.\n");
        exit(1);
    }
    return $value;
}

function guessContentTypeForRemote(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3', 'mpeg', 'mpga' => 'audio/mpeg',
        default => 'application/octet-stream',
    };
}

function shellExecOrFail(string $cmd, string $errorMessage): void
{
    exec($cmd, $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, $errorMessage . "\n");
        exit(1);
    }
}

function downloadToTemp(string $url, string $dest): bool
{
    $curlBin = trim((string) shell_exec('command -v curl 2>/dev/null'));
    if ($curlBin === '') {
        fwrite(STDERR, "curl command not found.\n");
        exit(1);
    }

    $cmd = escapeshellarg($curlBin)
        . ' -fL --retry 2 --connect-timeout 20 --max-time 300'
        . ' -o ' . escapeshellarg($dest)
        . ' ' . escapeshellarg($url)
        . ' 2>/dev/null';
    exec($cmd, $out, $code);
    return $code === 0 && is_file($dest) && filesize($dest) !== 0;
}

function buildOssObjectUrl(string $bucket, string $endpoint, string $objectKey): string
{
    return 'https://' . $bucket . '.' . $endpoint . '/' . str_replace('%2F', '/', rawurlencode($objectKey));
}

function uploadFileToOss(
    string $absolutePath,
    string $objectKey,
    string $bucket,
    string $endpoint,
    string $accessKeyId,
    string $accessKeySecret,
    string $contentType,
    string $cacheControl
): array {
    $curlBin = trim((string) shell_exec('command -v curl 2>/dev/null'));
    if ($curlBin === '') {
        fwrite(STDERR, "curl command not found.\n");
        exit(1);
    }

    $date = gmdate('D, d M Y H:i:s') . ' GMT';
    $canonicalHeaders = 'x-oss-storage-class:Standard';
    $resource = '/' . $bucket . '/' . $objectKey;
    $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$canonicalHeaders}\n{$resource}";
    $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret, true));
    $url = buildOssObjectUrl($bucket, $endpoint, $objectKey);
    $tmpResponse = tempnam(sys_get_temp_dir(), 'oss-upload-response-');
    if ($tmpResponse === false) {
        return ['ok' => false, 'status' => '0', 'body' => 'failed to create temp response file'];
    }

    $cmd = escapeshellarg($curlBin)
        . ' -sS -X PUT '
        . escapeshellarg($url)
        . ' -H ' . escapeshellarg('Date: ' . $date)
        . ' -H ' . escapeshellarg('Content-Type: ' . $contentType)
        . ' -H ' . escapeshellarg('Authorization: OSS ' . $accessKeyId . ':' . $signature)
        . ' -H ' . escapeshellarg('x-oss-storage-class: Standard')
        . ' -H ' . escapeshellarg('Cache-Control: ' . $cacheControl)
        . ' --data-binary @' . escapeshellarg($absolutePath)
        . ' -o ' . escapeshellarg($tmpResponse)
        . ' -w ' . escapeshellarg('%{http_code}');

    $status = trim((string) shell_exec($cmd));
    $body = is_file($tmpResponse) ? (string) file_get_contents($tmpResponse) : '';
    @unlink($tmpResponse);
    return [
        'ok' => preg_match('/^20[0-9]$/', $status) === 1,
        'status' => $status,
        'body' => trim($body),
    ];
}

$dryRun = in_array('--dry-run', $argv, true);
$accessKeyId = envRequired('OSS_ACCESS_KEY_ID');
$accessKeySecret = envRequired('OSS_ACCESS_KEY_SECRET');
$bucket = envRequired('OSS_BUCKET');
$region = envRequired('OSS_REGION');
$publicBaseUrl = rtrim(envRequired('OSS_PUBLIC_BASE_URL'), '/');
$endpoint = trim((string) getenv('OSS_ENDPOINT'));
if ($endpoint === '') {
    $endpoint = 'oss-' . $region . '.aliyuncs.com';
}
$cacheControl = trim((string) getenv('OSS_CACHE_CONTROL'));
if ($cacheControl === '') {
    $cacheControl = 'public, max-age=31536000, immutable';
}

$content = loadSiteContent();
$saveAfterEach = !in_array('--save-at-end', $argv, true);
$downloaded = [];
$uploaded = 0;
$replaced = 0;
$failed = 0;
$samples = [];
$visited = 0;

$walk = function (&$node) use (
    &$walk,
    &$content,
    $dryRun,
    $saveAfterEach,
    $accessKeyId,
    $accessKeySecret,
    $bucket,
    $endpoint,
    $publicBaseUrl,
    $cacheControl,
    &$downloaded,
    &$uploaded,
    &$replaced,
    &$failed,
    &$samples,
    &$visited
): void {
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

    if (!preg_match('#^https://[^/]+\.public\.blob\.vercel-storage\.com/uploads/.+#i', $node)) {
        return;
    }

    $visited++;
    fwrite(STDOUT, "[{$visited}] Processing {$node}\n");

    if (isset($downloaded[$node])) {
        if ($downloaded[$node] !== '') {
            $node = $downloaded[$node];
            $replaced++;
        } else {
            $failed++;
        }
        return;
    }

    $parts = parse_url($node);
    $path = (string) ($parts['path'] ?? '');
    if (!str_starts_with($path, '/uploads/')) {
        $downloaded[$node] = '';
        $failed++;
        return;
    }

    $newUrl = $publicBaseUrl . $path;
    if ($dryRun) {
        $downloaded[$node] = $newUrl;
        $node = $newUrl;
        $uploaded++;
        $replaced++;
        fwrite(STDOUT, "    Dry run rewrite -> {$newUrl}\n");
        if (count($samples) < 10) {
            $samples[] = $path;
        }
        return;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'blob-migrate-');
    if ($tmp === false) {
        $downloaded[$node] = '';
        $failed++;
        return;
    }

    $okDownload = downloadToTemp($node, $tmp);
    if (!$okDownload) {
        @unlink($tmp);
        $downloaded[$node] = '';
        $failed++;
        fwrite(STDOUT, "    Download failed\n");
        return;
    }

    $objectKey = ltrim($path, '/');
    $contentType = guessContentTypeForRemote($path);
    $uploadResult = uploadFileToOss($tmp, $objectKey, $bucket, $endpoint, $accessKeyId, $accessKeySecret, $contentType, $cacheControl);
    @unlink($tmp);

    if (!($uploadResult['ok'] ?? false)) {
        $downloaded[$node] = '';
        $failed++;
        $statusText = (string) ($uploadResult['status'] ?? '0');
        $bodyText = (string) ($uploadResult['body'] ?? '');
        fwrite(STDOUT, "    Upload failed for {$objectKey} (HTTP {$statusText})\n");
        if ($bodyText !== '') {
            fwrite(STDOUT, "    OSS response: {$bodyText}\n");
        }
        return;
    }

    $downloaded[$node] = $newUrl;
    $node = $newUrl;
    $uploaded++;
    $replaced++;
    fwrite(STDOUT, "    Uploaded -> {$newUrl}\n");
    if ($saveAfterEach) {
        saveSiteContent($content);
    }
    if (count($samples) < 10) {
        $samples[] = $path;
    }
};

$walk($content);

if ($dryRun) {
    echo "Dry run complete.\n";
    echo "Would upload objects: {$uploaded}\n";
    echo "Would replace URLs: {$replaced}\n";
    echo "Failures detected: {$failed}\n";
    if ($samples !== []) {
        echo "Sample object paths:\n";
        foreach ($samples as $sample) {
            echo " - {$sample}\n";
        }
    }
    exit(0);
}

saveSiteContent($content);
echo "Migration complete.\n";
echo "Uploaded objects: {$uploaded}\n";
echo "Replaced URLs: {$replaced}\n";
echo "Failures: {$failed}\n";
if ($samples !== []) {
    echo "Sample object paths:\n";
    foreach ($samples as $sample) {
        echo " - {$sample}\n";
    }
}
