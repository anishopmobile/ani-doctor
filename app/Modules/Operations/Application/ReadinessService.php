<?php

namespace App\Modules\Operations\Application;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReadinessService
{
    /** Console-only diagnostics: return check identifiers, never configuration values or exceptions. */
    public function inspect(): array
    {
        $checks = [];
        $check = function (string $name, callable $probe) use (&$checks): void {
            try {
                $checks[$name] = $probe() ? 'pass' : 'fail';
            } catch (\Throwable) {
                $checks[$name] = 'fail';
            }
        };
        $check('production_debug_off', fn () => app()->environment('production') && !config('app.debug'));
        $check('explicit_proxy_addresses', fn () => \App\Modules\Operations\Domain\ProxyAddresses::valid(config('operations.trusted_proxies', [])));
        $check('https_origin', function (): bool {
            $url = parse_url(config('app.url'));
            return ($url['scheme'] ?? '') === 'https' && !empty($url['host']) && !isset($url['user']) && !isset($url['pass']) && !isset($url['query']) && !isset($url['fragment']);
        });
        $check('app_key', function (): bool {
            $key = config('app.key');
            $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            return is_string($key) && Encrypter::supported($key, config('app.cipher'));
        });
        $check('secure_encrypted_session', fn () => config('session.secure') && config('session.http_only') && config('session.encrypt') && in_array(config('session.same_site'), ['lax', 'strict'], true));
        $check('shared_session_cache', fn () => config('session.driver') === 'database' && config('cache.default') === 'database');
        $check('mysql_innodb_utc', function (): bool {
            if (DB::getDriverName() !== 'mysql' || config('database.connections.mysql.timezone') !== '+00:00') {
                return false;
            }
            DB::select('SELECT 1');
            return !DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? AND ENGINE <> ?', ['BASE TABLE', 'InnoDB']);
        });
        $check('schema_current', function (): bool {
            if (!Schema::hasTable('migrations')) {
                return false;
            }
            $required = array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php')));
            return !array_diff($required, DB::table('migrations')->pluck('migration')->all());
        });
        $check('private_storage', function (): bool {
            $private = realpath(storage_path('app/private'));
            return $private && !is_link(public_path('storage')) && !app(\App\Modules\Operations\Infrastructure\PrivatePaths::class)->inside($private, public_path());
        });
        $check('runtime_writable', fn () => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')));
        $check('scheduler_heartbeat', function (): bool {
            $last = Cache::get('operations.scheduler_seen');
            return is_int($last) && $last <= time() && $last >= time() - 180;
        });
        $check('backup_key', fn () => strlen(app(\App\Modules\Operations\Infrastructure\PrivatePaths::class)->key()) === 32);
        $check('no_public_secrets', fn () => !file_exists(public_path('.env')) && !is_dir(public_path('vendor')) && !is_dir(public_path('storage')));
        return ['checks' => $checks, 'ready' => !in_array('fail', $checks, true), 'scope' => 'local_configuration_only', 'external_tls_backup_restore_and_provider_tests' => 'separate_evidence_required'];
    }
}
