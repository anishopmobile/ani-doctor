<?php

namespace App\Modules\Operations\Infrastructure;

use Generator;
use RuntimeException;

/** Versioned, streaming AES-256-GCM records; no database dump is written in plaintext. */
final class EncryptedArchive
{
    private const MAGIC = "ANI-DOCTOR-BACKUP-1\n";
    private const MAX_RECORD = 1048576;

    public function write($stream, string $key, iterable|callable $records): void
    {
        $salt = random_bytes(32);
        $prefix = random_bytes(8);
        $header = self::MAGIC.$salt.$prefix;
        $this->put($stream, $header);
        $derived = hash_hkdf('sha256', $key, 32, self::MAGIC, $salt);
        $sequence = 0;
        $emit = function (array $record) use ($stream, $derived, $header, $prefix, &$sequence): void {
            $this->record($stream, $derived, $header, $prefix, ++$sequence, $record);
        };
        if (is_callable($records)) {
            $records($emit);
        } else {
            foreach ($records as $record) {
                $emit($record);
            }
        }
        $this->record($stream, $derived, $header, $prefix, ++$sequence, ['type' => 'final']);
    }

    public function read($stream, string $key): Generator
    {
        $header = $this->take($stream, strlen(self::MAGIC) + 40);
        if (!str_starts_with($header, self::MAGIC)) {
            throw new RuntimeException('BACKUP_INVALID');
        }
        $derived = hash_hkdf('sha256', $key, 32, self::MAGIC, substr($header, strlen(self::MAGIC), 32));
        $prefix = substr($header, -8);
        for ($sequence = 1; $sequence < 0xffffffff; $sequence++) {
            $length = unpack('Nlength', $this->take($stream, 4))['length'];
            if ($length < 16 || $length > self::MAX_RECORD) {
                throw new RuntimeException('BACKUP_INVALID');
            }
            $body = $this->take($stream, $length);
            $nonce = $prefix.pack('N', $sequence);
            $plain = openssl_decrypt(substr($body, 16), 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, $nonce, substr($body, 0, 16), $header.pack('N', $sequence));
            if ($plain === false) {
                throw new RuntimeException('BACKUP_AUTHENTICATION_FAILED');
            }
            $record = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
            if (($record['type'] ?? '') === 'final') {
                if (fread($stream, 1) !== '') {
                    throw new RuntimeException('BACKUP_INVALID');
                }
                return;
            }
            yield $record;
        }
        throw new RuntimeException('BACKUP_INVALID');
    }

    private function record($stream, string $key, string $header, string $prefix, int $sequence, array $record): void
    {
        if ($sequence >= 0xffffffff) {
            throw new RuntimeException('BACKUP_TOO_LARGE');
        }
        $plain = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $prefix.pack('N', $sequence), $tag, $header.pack('N', $sequence), 16);
        if ($cipher === false || strlen($cipher) + 16 > self::MAX_RECORD) {
            throw new RuntimeException('BACKUP_WRITE_FAILED');
        }
        $this->put($stream, pack('N', strlen($cipher) + 16).$tag.$cipher);
    }

    private function take($stream, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && !feof($stream)) {
            $part = fread($stream, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }
        if (strlen($data) !== $length) {
            throw new RuntimeException('BACKUP_TRUNCATED');
        }
        return $data;
    }

    public function put($stream, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($stream, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException('BACKUP_WRITE_FAILED');
            }
            $bytes = substr($bytes, $written);
        }
    }
}
