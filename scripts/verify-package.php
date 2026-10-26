<?php

// Uses the installed, production-only dependencies and never boots the web application.
$options = getopt('', ['root:', 'write-manifest', 'revision:']);
$root = realpath($options['root'] ?? '');
if (!$root || !is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "PACKAGE_INCOMPLETE\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
try {
    $verifier = new App\Modules\Operations\Application\PackageVerifier();
    if (isset($options['write-manifest'])) {
        $revision = $options['revision'] ?? '';
        if (!preg_match('/\A[a-f0-9]{40}\z/', $revision)) {
            throw new RuntimeException('PACKAGE_REVISION_INVALID');
        }
        $manifest = ['format' => 1, 'revision' => $revision, 'created_at' => gmdate(DATE_ATOM), 'files' => $verifier->files($root)];
        file_put_contents($root.'/RELEASE.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    $count = $verifier->verify($root);
    echo json_encode(['verified' => true, 'files' => $count, 'signature' => false, 'note' => 'Checksums detect changes; verify the trusted Actions artifact digest separately.'])."\n";
} catch (Throwable) {
    fwrite(STDERR, "PACKAGE_VERIFICATION_FAILED\n");
    exit(1);
}
