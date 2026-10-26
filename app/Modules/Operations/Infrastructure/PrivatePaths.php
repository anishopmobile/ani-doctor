<?php

namespace App\Modules\Operations\Infrastructure;

use RuntimeException;

final class PrivatePaths
{
    public function directory(string $path): string
    {
        if ($path === '' || !preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) || is_link($path)) {
            throw new RuntimeException('PRIVATE_PATH_INVALID');
        }
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('PRIVATE_PATH_UNWRITABLE');
        }
        $resolved = realpath($path);
        if (!$resolved || $this->inside($resolved, public_path())) {
            throw new RuntimeException('PRIVATE_PATH_PUBLIC');
        }
        return $resolved;
    }

    public function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        return $path === $root || str_starts_with($path, $root.'/');
    }

    public function member(string $name): string
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, ':') || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $name)) {
            throw new RuntimeException('BACKUP_MEMBER_INVALID');
        }
        return $name;
    }

    public function key(): string
    {
        $file = (string) config('operations.backup_key_file');
        $resolved = realpath($file);
        if (!$resolved || is_link($file) || $this->inside($resolved, public_path()) || !is_file($resolved) || filesize($resolved) > 100) {
            throw new RuntimeException('BACKUP_KEY_UNAVAILABLE');
        }
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($resolved) & 0077) !== 0) {
            throw new RuntimeException('BACKUP_KEY_PERMISSIONS');
        }
        $key = base64_decode(trim(file_get_contents($resolved)), true);
        $appKey = (string) config('app.key');
        $appKey = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if ($key === false || strlen($key) !== 32 || (is_string($appKey) && hash_equals($appKey, $key))) {
            throw new RuntimeException('BACKUP_KEY_INVALID');
        }
        return $key;
    }
}
