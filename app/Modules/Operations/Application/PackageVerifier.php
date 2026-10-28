<?php

namespace App\Modules\Operations\Application;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PackageVerifier
{
    public function files(string $root): array
    {
        $root = realpath($root);
        if (!$root) {
            throw new RuntimeException('PACKAGE_ROOT_INVALID');
        }
        foreach (['artisan', 'composer.lock', 'public/index.php', 'vendor/autoload.php', '.env.example', 'resources/fonts/Vazirmatn-Regular.ttf'] as $required) {
            if (!is_file($root.'/'.$required)) {
                throw new RuntimeException('PACKAGE_INCOMPLETE');
            }
        }
        foreach (['vendor/phpunit', 'vendor/fakerphp', 'vendor/laravel/pint', 'vendor/mockery'] as $development) {
            if (file_exists($root.'/'.$development)) {
                throw new RuntimeException('PACKAGE_HAS_DEVELOPMENT_DEPENDENCIES');
            }
        }
        $files = [];
        $allowed = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor', 'storage', 'scripts', 'deployment', 'docs', 'artisan', 'composer.json', 'composer.lock', '.env.example', 'DEPLOYMENT.md', 'INSTALL.txt', 'RELEASE.json'];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $top = explode('/', $path)[0];
            if ($file->isLink() || !in_array($top, $allowed, true)) {
                throw new RuntimeException('PACKAGE_UNEXPECTED_ENTRY');
            }
            if (!$file->isFile()) {
                continue;
            }
            if ($top === 'docs' && !in_array($path, ['docs/INSTALL-LOCAL.md', 'docs/INSTALL-SHARED-HOSTING.md', 'docs/P8.md', 'docs/P8-VALIDATION.md', 'docs/WEBSITE-CMS-PLAN.md', 'docs/WEBSITE-CMS-VALIDATION.md', 'docs/INSTALLATION-AUDIT.md', 'docs/UI-CMS-REDESIGN-AUDIT.md', 'docs/UI-CMS-VALIDATION.md', 'docs/GUIDED-BOOKING-UX.md'], true)) {
                throw new RuntimeException('PACKAGE_UNEXPECTED_ENTRY');
            }
            if ($top !== 'vendor') {
                $base = basename($path);
                if (($base === '.env' || str_starts_with($base, '.env.')) && $path !== '.env.example') {
                    throw new RuntimeException('PACKAGE_PRIVATE_DATA');
                }
                if (preg_match('/\.(?:sql|sqlite3?|pem|key|adb|log|part|csv|xlsx|pdf|bak|dump)$/i', $base)
                    || (str_starts_with($path, 'storage/') && $base !== '.gitignore')
                    || (str_starts_with($path, 'bootstrap/cache/') && $base !== '.gitignore')
                    || (str_starts_with($path, 'public/') && str_ends_with(strtolower($path), '.php') && $path !== 'public/index.php')) {
                    throw new RuntimeException('PACKAGE_PRIVATE_DATA');
                }
            }
            if ($file->isFile() && $path !== 'RELEASE.json') {
                $files[$path] = hash_file('sha256', $file->getPathname());
            }
        }
        $example = file_get_contents($root.'/.env.example');
        foreach (['APP_KEY', 'DB_PASSWORD', 'SMS_IR_API_KEY', 'ZARINPAL_MERCHANT_ID', 'OPS_RESTORE_DB_PASSWORD'] as $name) {
            if (preg_match('/^'.preg_quote($name, '/').'=([^\r\n]*)\r?$/m', $example, $match) && trim($match[1], " \t\"'") !== '') {
                throw new RuntimeException('PACKAGE_EXAMPLE_HAS_SECRET');
            }
        }
        ksort($files);
        return $files;
    }

    public function verify(string $root): int
    {
        $manifest = json_decode(file_get_contents($root.'/RELEASE.json'), true, 8, JSON_THROW_ON_ERROR);
        $files = $this->files($root);
        if (($manifest['format'] ?? null) !== 1 || ($manifest['files'] ?? null) !== $files) {
            throw new RuntimeException('PACKAGE_CHECKSUM_MISMATCH');
        }
        return count($files);
    }
}
