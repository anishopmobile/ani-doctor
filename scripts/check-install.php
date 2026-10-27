<?php

// Deliberately bootable before Composer: an unsupported PHP must be diagnosed first.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$checks = ['php_8_4_or_newer' => PHP_VERSION_ID >= 80400];
foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'iconv', 'intl', 'libxml', 'mbstring', 'openssl', 'PDO', 'session', 'SimpleXML', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib'] as $extension) {
    $checks['extension_'.$extension] = extension_loaded($extension);
}
$root = dirname(__DIR__);
$checks['vendor_autoload'] = is_file($root.'/vendor/autoload.php');
if (in_array(false, $checks, true)) {
    echo json_encode(['runtime_ready' => false, 'php_version' => PHP_VERSION, 'checks' => $checks], JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(1);
}
try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $status = $kernel->call('ops:installation-check');
    echo $kernel->output();
    exit($status);
} catch (Throwable) {
    // Raw bootstrap/database errors can contain paths, credentials and private bindings.
    fwrite(STDERR, "INSTALLATION_BOOTSTRAP_FAILED\n");
    exit(1);
}
