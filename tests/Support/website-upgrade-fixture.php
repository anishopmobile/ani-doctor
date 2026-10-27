<?php

// NEVER shipped in the ZIP. Destructive schema simulation requires this disposable CI DB.
if (getenv('P8_SYNTHETIC_FIXTURE') !== '1') {
    fwrite(STDERR, "ISOLATED_UPGRADE_FIXTURE_REQUIRED\n");
    exit(1);
}
require '/var/www/app/vendor/autoload.php';
$app = require '/var/www/app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (config('database.connections.mysql.database') !== 'ani_doctor_p8_smoke'
    || config('app.url') !== 'https://localhost:18443'
    || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    fwrite(STDERR, "ISOLATED_UPGRADE_FIXTURE_REQUIRED\n");
    exit(1);
}
try {
    $snapshot = static function (): array {
        $result = [];
        foreach (['users', 'patients', 'services', 'appointments', 'payments', 'wallet_entries'] as $table) {
            $result[$table] = hash('sha256', json_encode(Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $result;
    };
    $status = static function (string $path): int {
        $curl = curl_init('https://localhost:18443'.$path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CAINFO => '/etc/ssl/certs/p8.crt', CURLOPT_TIMEOUT => 20]);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($body === false) {
            throw new RuntimeException('UPGRADE_HTTP_PROBE_FAILED');
        }

        return $code;
    };
    $before = $snapshot();
    if ($kernel->call('migrate:rollback', ['--step' => 8, '--force' => true]) !== 0) {
        throw new RuntimeException('UPGRADE_LEGACY_SCHEMA_FAILED');
    }
    $legacy = app(App\Modules\Operations\Application\InstallationCheckService::class)->inspect();
    if ($legacy['ready'] || count($legacy['pending_migrations']) !== 8 || $status('/') !== 500) {
        throw new RuntimeException('UPGRADE_REPRODUCTION_FAILED');
    }
    if ($kernel->call('migrate', ['--force' => true]) !== 0
        || $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true]) !== 0) {
        throw new RuntimeException('UPGRADE_MIGRATION_FAILED');
    }
    $current = app(App\Modules\Operations\Application\InstallationCheckService::class)->inspect();
    if (!$current['ready'] || $snapshot() !== $before) {
        throw new RuntimeException('UPGRADE_DATA_PRESERVATION_FAILED');
    }
    foreach (['/', '/services', '/articles', '/sitemap.xml', '/calendar'] as $path) {
        if ($status($path) !== 200) {
            throw new RuntimeException('UPGRADE_PUBLIC_PAGE_FAILED');
        }
    }
    if ($kernel->call('ops:installation-check') !== 0) {
        throw new RuntimeException('UPGRADE_INSTALLATION_CHECK_FAILED');
    }
    echo json_encode(['upgrade_passed' => true, 'legacy_500_reproduced' => true, 'pending_before' => 8, 'pending_after' => 0, 'core_data_preserved' => true, 'public_pages' => 5, 'scope' => 'synthetic_isolated_packaged_upgrade_only']).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "PACKAGED_UPGRADE_CHECK_FAILED\n");
    exit(1);
}
