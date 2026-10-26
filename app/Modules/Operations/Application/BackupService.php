<?php

namespace App\Modules\Operations\Application;

use App\Modules\Operations\Infrastructure\EncryptedArchive;
use App\Modules\Operations\Infrastructure\MysqlSnapshot;
use App\Modules\Operations\Infrastructure\PrivatePaths;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupService
{
    public function __construct(private EncryptedArchive $archive, private MysqlSnapshot $database, private PrivatePaths $paths)
    {
    }

    public function create(bool $writersStopped): array
    {
        if (!app()->isDownForMaintenance() || !$writersStopped) {
            throw new RuntimeException('BACKUP_REQUIRES_MAINTENANCE_AND_STOPPED_WRITERS');
        }
        $key = $this->paths->key();
        $appKey = (string) config('app.key');
        $decoded = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if (!is_string($decoded) || !\Illuminate\Encryption\Encrypter::supported($decoded, config('app.cipher'))) {
            throw new RuntimeException('BACKUP_REQUIRES_APP_KEY');
        }
        $root = $this->root();
        $private = $this->paths->directory(storage_path('app/private'));
        $keyPath = realpath(config('operations.backup_key_file'));
        if ($this->paths->inside($root, $private) || $this->paths->inside($keyPath, $private) || $this->paths->inside($keyPath, $root)) {
            throw new RuntimeException('BACKUP_PATH_OVERLAP');
        }
        $work = $this->work($root);
        $name = 'ani-doctor-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(8)).'.adb';
        $temporary = $root.'/'.$name.'.part';
        $stream = fopen($temporary, 'xb');
        chmod($temporary, 0600);
        try {
            $defaults = $this->database->defaults($work);
            $this->archive->write($stream, $key, function (callable $emit) use ($defaults, $private): void {
                $emit(['type' => 'meta', 'version' => 1, 'created_at' => now()->toIso8601String(), 'app_key_hash' => hash('sha256', config('app.key'))]);
                $emit(['type' => 'begin', 'path' => 'database.sql']);
                $hash = hash_init('sha256');
                $this->database->dump($defaults, function (string $bytes) use ($emit, $hash): void {
                    hash_update($hash, $bytes);
                    $this->chunks($bytes, $emit);
                });
                $emit(['type' => 'end', 'sha256' => hash_final($hash)]);
                $files = 0;
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isLink()) {
                        throw new RuntimeException('BACKUP_SYMLINK_FORBIDDEN');
                    }
                    if (!$file->isFile()) {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($private) + 1));
                    $emit(['type' => 'begin', 'path' => 'private/'.$this->paths->member($relative)]);
                    $input = fopen($file->getPathname(), 'rb');
                    $hash = hash_init('sha256');
                    try {
                        while (!feof($input)) {
                            $bytes = fread($input, 65536);
                            if ($bytes === false) {
                                throw new RuntimeException('BACKUP_READ_FAILED');
                            }
                            hash_update($hash, $bytes);
                            $this->chunks($bytes, $emit);
                        }
                    } finally {
                        fclose($input);
                    }
                    $emit(['type' => 'end', 'sha256' => hash_final($hash)]);
                    $files++;
                }
                $emit(['type' => 'manifest', 'private_files' => $files]);
            });
            fflush($stream);
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $root.'/'.$name)) {
                throw new RuntimeException('BACKUP_WRITE_FAILED');
            }
            return ['file' => $name, 'sha256' => hash_file('sha256', $root.'/'.$name), 'bytes' => filesize($root.'/'.$name)];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
            $this->cleanWork($work, $root);
        }
    }

    public function restore(string $file, string $target, string $privateTarget): array
    {
        $this->database->assertEmpty($target);
        $root = $this->root();
        $resolved = realpath($file);
        if (!$resolved || is_link($file) || $this->paths->inside($resolved, public_path())) {
            throw new RuntimeException('BACKUP_INVALID');
        }
        $parent = $this->paths->directory(dirname($privateTarget));
        $privateTarget = $parent.'/'.basename($privateTarget);
        if (file_exists($privateTarget) || $this->paths->inside($privateTarget, storage_path()) || $this->paths->inside($privateTarget, public_path())) {
            throw new RuntimeException('RESTORE_PRIVATE_TARGET_INVALID');
        }
        $work = $this->work($root);
        $input = fopen($resolved, 'rb');
        try {
            $files = $this->unpack($input, $work, $this->paths->key());
            // No database writes happen until every tag, checksum, final record and APP_KEY match.
            $defaults = $this->database->defaults($work, true);
            $this->database->restore($defaults, $target, $work.'/database.sql');
            if (!rename($work.'/private', $privateTarget)) {
                throw new RuntimeException('RESTORE_PRIVATE_MOVE_FAILED');
            }
            return ['database' => $target, 'private_files' => $files, 'promoted' => false];
        } finally {
            fclose($input);
            $this->cleanWork($work, $root);
        }
    }

    private function unpack($input, string $work, string $key): int
    {
        $open = null;
        $hash = null;
        $seen = [];
        $metadata = false;
        $manifest = false;
        mkdir($work.'/private', 0700);
        try {
            foreach ($this->archive->read($input, $key) as $record) {
                $type = $record['type'] ?? '';
                if ($type === 'meta' && !$metadata && !$seen && !$open) {
                    if (($record['version'] ?? null) !== 1 || !hash_equals(hash('sha256', config('app.key')), $record['app_key_hash'] ?? '')) {
                        throw new RuntimeException('RESTORE_APP_KEY_MISMATCH');
                    }
                    $metadata = true;
                } elseif ($type === 'begin' && $metadata && !$manifest && !$open) {
                    $path = $this->paths->member($record['path'] ?? '');
                    if (isset($seen[$path]) || ($path !== 'database.sql' && !str_starts_with($path, 'private/'))) {
                        throw new RuntimeException('BACKUP_MEMBER_INVALID');
                    }
                    $seen[$path] = true;
                    $destination = $work.'/'.$path;
                    if (!is_dir(dirname($destination))) {
                        mkdir(dirname($destination), 0700, true);
                    }
                    $open = fopen($destination, 'xb');
                    chmod($destination, 0600);
                    $hash = hash_init('sha256');
                } elseif ($type === 'data' && $open && is_string($record['bytes'] ?? null)) {
                    $bytes = base64_decode($record['bytes'], true);
                    if ($bytes === false) {
                        throw new RuntimeException('BACKUP_INVALID');
                    }
                    hash_update($hash, $bytes);
                    $this->archive->put($open, $bytes);
                } elseif ($type === 'end' && $open) {
                    if (!hash_equals(hash_final($hash), $record['sha256'] ?? '')) {
                        throw new RuntimeException('BACKUP_CHECKSUM_FAILED');
                    }
                    fclose($open);
                    $open = null;
                } elseif ($type === 'manifest' && !$open && !$manifest && isset($seen['database.sql']) && ($record['private_files'] ?? -1) === count($seen) - 1) {
                    $manifest = true;
                } else {
                    throw new RuntimeException('BACKUP_INVALID');
                }
            }
            if (!$metadata || !$manifest || $open) {
                throw new RuntimeException('BACKUP_INVALID');
            }
            return count($seen) - 1;
        } finally {
            if (is_resource($open)) {
                fclose($open);
            }
        }
    }

    private function chunks(string $bytes, callable $emit): void
    {
        foreach (str_split($bytes, 65536) as $part) {
            $emit(['type' => 'data', 'bytes' => base64_encode($part)]);
        }
    }

    private function root(): string
    {
        return $this->paths->directory(config('operations.backup_directory') ?: storage_path('app/backups'));
    }

    private function work(string $root): string
    {
        $path = $root.'/.work-'.bin2hex(random_bytes(16));
        mkdir($path, 0700);
        return $path;
    }

    private function cleanWork(string $work, string $root): void
    {
        // Resolve and bound the recursive cleanup to the private, newly allocated work directory.
        if (is_dir($work) && $this->paths->inside(realpath($work), $root) && str_starts_with(basename($work), '.work-')) {
            File::deleteDirectory($work);
        }
    }
}
