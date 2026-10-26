<?php

namespace Tests\Feature;

use App\Modules\Operations\Application\PackageVerifier;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class P8PackageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/ani-doctor-package-'.bin2hex(random_bytes(12));
        foreach (['artisan', 'composer.lock', 'public/index.php', 'vendor/autoload.php', '.env.example', 'resources/fonts/Vazirmatn-Regular.ttf'] as $path) {
            $this->writeFixture($path, 'synthetic');
        }
        $this->writeFixture('.env.example', "APP_KEY=\nDB_PASSWORD=\nSMS_IR_API_KEY=\nZARINPAL_MERCHANT_ID=\n");
    }

    protected function tearDown(): void
    {
        $path = realpath($this->root);
        if ($path && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', realpath(sys_get_temp_dir())).'/ani-doctor-package-')) {
            File::deleteDirectory($path);
        }
        parent::tearDown();
    }

    private function writeFixture(string $name, string $bytes): void
    {
        if (!is_dir(dirname($this->root.'/'.$name))) {
            mkdir(dirname($this->root.'/'.$name), 0700, true);
        }
        file_put_contents($this->root.'/'.$name, $bytes);
    }

    public function test_release_manifest_detects_changed_and_additional_files(): void
    {
        $verifier = new PackageVerifier();
        $files = $verifier->files($this->root);
        $this->writeFixture('RELEASE.json', json_encode(['format' => 1, 'files' => $files]));
        $this->assertSame(count($files), $verifier->verify($this->root));
        $this->writeFixture('public/index.php', 'changed');
        $this->expectExceptionMessage('PACKAGE_CHECKSUM_MISMATCH');
        $verifier->verify($this->root);
    }

    public function test_zip_rejects_secrets_runtime_backups_source_tests_and_public_php(): void
    {
        foreach (['.env', 'public/.env', 'storage/app/private/answer.json', 'storage/app/backups/backup.adb', 'bootstrap/cache/config.php', 'public/shell.php', 'public/patient-report.xlsx', 'public/ticket.pdf', 'tests/fixture.php'] as $path) {
            $this->writeFixture($path, 'PRIVATE-SENTINEL');
            try {
                (new PackageVerifier())->files($this->root);
                $this->fail('Unsafe package accepted.');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
            unlink($this->root.'/'.$path);
        }
    }

    public function test_example_credentials_and_development_dependencies_are_rejected(): void
    {
        $this->writeFixture('.env.example', "APP_KEY=PRIVATE-KEY\n");
        try {
            (new PackageVerifier())->files($this->root);
            $this->fail('Example secret accepted.');
        } catch (\RuntimeException $error) {
            $this->assertSame('PACKAGE_EXAMPLE_HAS_SECRET', $error->getMessage());
        }
        $this->writeFixture('.env.example', "APP_KEY=\n");
        $this->writeFixture('vendor/phpunit/phpunit/composer.json', '{}');
        $this->expectExceptionMessage('PACKAGE_HAS_DEVELOPMENT_DEPENDENCIES');
        (new PackageVerifier())->files($this->root);
    }
}
