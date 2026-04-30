<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
echo 'upload_max_filesize=' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'post_max_size=' . ini_get('post_max_size') . PHP_EOL;
