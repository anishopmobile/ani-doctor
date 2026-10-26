<?php

namespace Tests\Feature;

use App\Modules\Operations\Infrastructure\EncryptedArchive;
use App\Modules\Operations\Infrastructure\PrivatePaths;
use Tests\TestCase;

final class P8ArchiveTest extends TestCase
{
    public function test_archive_roundtrip_is_authenticated_and_contains_no_plaintext(): void
    {
        $archive = new EncryptedArchive();
        $stream = fopen('php://temp', 'w+b');
        $key = random_bytes(32);
        $records = [['type' => 'data', 'bytes' => 'PRIVATE-PATIENT-DATA-۱۲۳-ی/ک'], ['type' => 'manifest', 'count' => 1]];
        $archive->write($stream, $key, $records);
        rewind($stream);
        $bytes = stream_get_contents($stream);
        $this->assertStringNotContainsString('PRIVATE-PATIENT', $bytes);
        rewind($stream);
        $this->assertSame($records, iterator_to_array($archive->read($stream, $key)));
        fclose($stream);
    }

    public function test_wrong_key_tampering_truncation_append_and_record_reordering_are_rejected(): void
    {
        $archive = new EncryptedArchive();
        $key = random_bytes(32);
        $stream = fopen('php://temp', 'w+b');
        $archive->write($stream, $key, [['type' => 'meta'], ['type' => 'data', 'bytes' => 'synthetic']]);
        rewind($stream);
        $valid = stream_get_contents($stream);
        fclose($stream);
        $flipped = $valid;
        $flipped[90] = chr(ord($flipped[90]) ^ 1);
        $offset = strlen("ANI-DOCTOR-BACKUP-1\n") + 40;
        $firstSize = 4 + unpack('Nlength', substr($valid, $offset, 4))['length'];
        $secondSize = 4 + unpack('Nlength', substr($valid, $offset + $firstSize, 4))['length'];
        $reordered = substr($valid, 0, $offset).substr($valid, $offset + $firstSize, $secondSize).substr($valid, $offset, $firstSize).substr($valid, $offset + $firstSize + $secondSize);
        foreach ([[$valid, random_bytes(32)], [$flipped, $key], [substr($valid, 0, -1), $key], [$valid.'appended', $key], [$reordered, $key], [substr($valid, 0, $offset + $firstSize + $secondSize), $key]] as [$bytes, $candidate]) {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $bytes);
            rewind($stream);
            try {
                iterator_to_array($archive->read($stream, $candidate));
                $this->fail('Untrusted or incomplete archive accepted.');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            } finally {
                fclose($stream);
            }
        }
    }

    public function test_archive_member_paths_cannot_escape_a_restore_directory(): void
    {
        foreach (['../secret', '/absolute', 'private/../key', 'C:/secret', 'private\\secret', "private/\0secret"] as $path) {
            try {
                app(PrivatePaths::class)->member($path);
                $this->fail('Unsafe member accepted.');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('private/نمونه.txt', app(PrivatePaths::class)->member('private/نمونه.txt'));
    }

    public function test_backup_requires_a_real_maintenance_window_and_no_web_command_exists(): void
    {
        $this->artisan('ops:backup')->assertFailed();
        $this->artisan('ops:restore', ['archive' => 'missing', 'target' => 'ani_doctor_restore_test', 'private-target' => '/unused'])->assertFailed();
        $this->get('/ops/backup')->assertNotFound();
    }
}
