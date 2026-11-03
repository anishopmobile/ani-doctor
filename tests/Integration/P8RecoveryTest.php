<?php

namespace Tests\Integration;

use App\Modules\Operations\Application\BackupService;
use App\Modules\Operations\Infrastructure\MysqlSnapshot;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\P7Fixtures;
use Tests\TestCase;

final class P8RecoveryTest extends TestCase
{
    use DatabaseMigrations;
    use P7Fixtures;

    private $admin;
    private $patient;
    private $secretary;
    private $service;
    private string $scratch;
    private string $target;
    private string $originalStorage;
    private string $restoreUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->scratch = sys_get_temp_dir().'/ani-doctor-p8-'.bin2hex(random_bytes(12));
        mkdir($this->scratch, 0700);
        mkdir($this->scratch.'/storage/framework', 0700, true);
        mkdir($this->scratch.'/storage/app/private', 0700, true);
        $this->originalStorage = storage_path();
        $this->app->useStoragePath($this->scratch.'/storage');
        $keyFile = $this->scratch.'/backup.key';
        file_put_contents($keyFile, base64_encode(random_bytes(32)));
        chmod($keyFile, 0600);
        config(['operations.backup_directory' => $this->scratch.'/backups', 'operations.backup_key_file' => $keyFile]);
        if (PHP_OS_FAMILY === 'Windows') {
            config(['operations.mysql' => 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe', 'operations.mysqldump' => 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe']);
        }
        $this->target = 'ani_doctor_restore_'.bin2hex(random_bytes(8));
        DB::statement('CREATE DATABASE `'.$this->target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->restoreUser = 'p8_restore_'.bin2hex(random_bytes(6));
        $password = bin2hex(random_bytes(24));
        DB::statement("CREATE USER '".$this->restoreUser."'@'%' IDENTIFIED BY '".$password."'");
        DB::statement('GRANT ALL PRIVILEGES ON `'.str_replace('_', '\\_', $this->target)."`.* TO '".$this->restoreUser."'@'%'");
        config(['operations.restore_username' => $this->restoreUser, 'operations.restore_password' => $password]);
        config(['database.connections.p8_restore' => [...config('database.connections.mysql'), 'database' => $this->target]]);
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
        $this->offer();
        $this->app->maintenanceMode()->activate(['time' => time()]);
    }

    protected function tearDown(): void
    {
        try {
            // EMR fixtures belong only to this isolated recovery test; ordinary down() protects real history.
            DB::table('emr_documents')->delete();
            foreach (DB::table('emr_records')->orderByDesc('id')->pluck('id') as $id) {
                DB::table('emr_records')->where('id', $id)->delete();
            }
            $this->app->maintenanceMode()->deactivate();
            $this->app->useStoragePath($this->originalStorage);
            DB::purge('p8_restore');
            DB::purge('operations_restore');
            if (preg_match('/\Aani_doctor_restore_[a-f0-9]{16}\z/', $this->target)) {
                DB::statement('DROP DATABASE `'.$this->target.'`');
            }
            if (preg_match('/\Ap8_restore_[a-f0-9]{12}\z/', $this->restoreUser)) {
                DB::statement("DROP USER '".$this->restoreUser."'@'%'");
            }
            $resolved = realpath($this->scratch);
            if ($resolved && str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', realpath(sys_get_temp_dir())).'/ani-doctor-p8-')) {
                File::deleteDirectory($resolved);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_real_encrypted_mysql_and_private_files_restore_preserves_finance_and_encrypted_answers(): void
    {
        $appointment = $this->cancel($this->collect($this->discounted()));
        $form = app(\App\Modules\DynamicForms\Application\PrivateFormService::class)->publish($this->admin, null, ['title' => 'فرم ساختگی بازیابی', 'service_id' => $this->service->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'تست', 'schema' => [['key' => 'note', 'label' => 'اطلاعات ضروری', 'type' => 'text', 'required' => true]]]);
        $active = $this->discounted(['start_at' => $appointment->start_at->addHour()->toIso8601String()]);
        app(\App\Modules\DynamicForms\Application\PrivateFormService::class)->submit($this->bookingActor($this->patient), $active, $form, ['version_id' => DB::table('form_versions')->sole()->id, 'expected_submission_id' => 0, 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'consent' => 1, 'answers' => ['note' => 'PRIVATE-RESTORE-SYNTHETIC-ی/ک']]);
        file_put_contents(storage_path('app/private/sample.txt'), 'PRIVATE-FILE-SYNTHETIC');
        $emr = app(\App\Modules\MedicalRecords\Application\ClinicalService::class)->save($this->admin, $appointment->patient, 'visit', ['request_key' => (string) \Illuminate\Support\Str::uuid(), 'occurred_jalali' => '1405-08-08', 'occurred_time' => '10:00', 'payload' => ['complaint' => 'EMR-RESTORE-SYNTHETIC']]);
        $emrDocument = app(\App\Modules\MedicalRecords\Application\DocumentService::class)->upload($this->admin, $appointment->patient, \Illuminate\Http\UploadedFile::fake()->image('synthetic.png'), ['request_key' => (string) \Illuminate\Support\Str::uuid(), 'visit_id' => $emr->id, 'title' => 'EMR-DOCUMENT-SYNTHETIC']);
        $before = app(\App\Modules\Reporting\Application\ReportService::class)->build($this->admin, $this->reportFilters())['reconciliation'];
        $backup = app(BackupService::class)->create(true);
        $path = $this->scratch.'/backups/'.$backup['file'];
        $this->assertSame(hash_file('sha256', $path), $backup['sha256']);
        $this->assertStringNotContainsString('PRIVATE-', file_get_contents($path));
        $restored = app(BackupService::class)->restore($path, $this->target, $this->scratch.'/restored-private');
        $this->assertFalse($restored['promoted']);
        $this->assertSame(2, $restored['private_files']);
        $db = DB::connection('p8_restore');
        $restoredPayload = json_decode(Crypt::decryptString($db->table('emr_records')->where('id', $emr->id)->value('payload')), true);
        $this->assertSame('EMR-RESTORE-SYNTHETIC', $restoredPayload['complaint']);
        $this->assertSame($emrDocument->sha256, hash('sha256', Crypt::decryptString(file_get_contents($this->scratch.'/restored-private/emr/'.$emrDocument->path))));
        $this->assertSame($emr->id, (int) $db->table('emr_documents')->where('id', $emrDocument->id)->value('visit_id'));
        $this->assertSame(800000, (int) $db->table('payments')->sum('amount_irr'));
        $this->assertSame(800000, (int) $db->table('wallet_entries')->sum('amount_irr'));
        $this->assertSame(2, $db->table('appointments')->count());
        $this->assertSame(DB::table('form_submissions')->sole()->answers, $db->table('form_submissions')->sole()->answers);
        $this->assertStringContainsString('PRIVATE-RESTORE-SYNTHETIC', Crypt::decryptString($db->table('form_submissions')->sole()->answers));
        $this->assertSame('PRIVATE-FILE-SYNTHETIC', file_get_contents($this->scratch.'/restored-private/sample.txt'));
        $this->assertSame(0, $before['اختلاف تطبیق ریال (باید صفر باشد)']);
        DB::setDefaultConnection('p8_restore');
        try {
            $after = app(\App\Modules\Reporting\Application\ReportService::class)->build($this->admin, $this->reportFilters())['reconciliation'];
            $this->assertSame($before, $after);
        } finally {
            DB::setDefaultConnection('mysql');
        }
        $this->assertSame([], glob($this->scratch.'/backups/.work-*'));
    }

    public function test_corrupted_archive_is_rejected_before_any_target_database_write(): void
    {
        $this->collect($this->discounted());
        $backup = app(BackupService::class)->create(true);
        $path = $this->scratch.'/backups/'.$backup['file'];
        $data = file_get_contents($path);
        $data[strlen($data) - 10] = chr(ord($data[strlen($data) - 10]) ^ 1);
        file_put_contents($path, $data);
        try {
            app(BackupService::class)->restore($path, $this->target, $this->scratch.'/restored-private');
            $this->fail('Corrupted backup was restored.');
        } catch (\RuntimeException) {
            $this->assertSame([], DB::connection('p8_restore')->select('SHOW TABLES'));
            $this->assertFileDoesNotExist($this->scratch.'/restored-private');
            $this->assertSame([], glob($this->scratch.'/backups/.work-*'));
        }
    }

    public function test_wrong_app_key_and_nonempty_or_active_targets_are_rejected(): void
    {
        $backup = app(BackupService::class)->create(true);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        try {
            app(BackupService::class)->restore($this->scratch.'/backups/'.$backup['file'], $this->target, $this->scratch.'/restored-private');
            $this->fail('Mismatched APP_KEY accepted.');
        } catch (\RuntimeException $error) {
            $this->assertSame('RESTORE_APP_KEY_MISMATCH', $error->getMessage());
            $this->assertSame([], DB::connection('p8_restore')->select('SHOW TABLES'));
        }
        foreach ([config('database.connections.mysql.database'), 'arbitrary_database'] as $target) {
            try {
                app(MysqlSnapshot::class)->assertEmpty($target);
                $this->fail('Active/unapproved target accepted.');
            } catch (\RuntimeException $error) {
                $this->assertSame('RESTORE_TARGET_INVALID', $error->getMessage());
            }
        }
        DB::connection('p8_restore')->statement('CREATE TABLE sentinel (id INT) ENGINE=InnoDB');
        $this->expectExceptionMessage('RESTORE_TARGET_NOT_EMPTY');
        app(MysqlSnapshot::class)->assertEmpty($this->target);
    }

    public function test_restore_credentials_with_source_database_access_are_rejected(): void
    {
        $source = str_replace('_', '\\_', config('database.connections.mysql.database'));
        DB::statement('GRANT SELECT ON `'.$source."`.* TO '".$this->restoreUser."'@'%'");
        $this->expectExceptionMessage('RESTORE_CREDENTIALS_NOT_ISOLATED');
        app(MysqlSnapshot::class)->assertEmpty($this->target);
    }

    public function test_authenticated_sql_cannot_switch_to_or_modify_the_active_database(): void
    {
        $source = str_replace('`', '``', config('database.connections.mysql.database'));
        $sql = "CREATE TABLE restore_probe (id INT) ENGINE=InnoDB;\nUSE `".$source."`;\nUPDATE services SET price_irr=0;\n";
        $path = $this->scratch.'/crafted.adb';
        $stream = fopen($path, 'wb');
        app(\App\Modules\Operations\Infrastructure\EncryptedArchive::class)->write($stream, base64_decode(file_get_contents($this->scratch.'/backup.key')), [
            ['type' => 'meta', 'version' => 1, 'app_key_hash' => hash('sha256', config('app.key'))],
            ['type' => 'begin', 'path' => 'database.sql'],
            ['type' => 'data', 'bytes' => base64_encode($sql)],
            ['type' => 'end', 'sha256' => hash('sha256', $sql)],
            ['type' => 'manifest', 'private_files' => 0],
        ]);
        fclose($stream);
        try {
            app(BackupService::class)->restore($path, $this->target, $this->scratch.'/restored-private');
            $this->fail('Cross-database SQL was accepted.');
        } catch (\RuntimeException $error) {
            $this->assertSame('DATABASE_RESTORE_FAILED', $error->getMessage());
            $this->assertSame(1000000, (int) DB::table('services')->where('id', $this->service->id)->value('price_irr'));
            $this->assertFileDoesNotExist($this->scratch.'/restored-private');
        }
    }
}
