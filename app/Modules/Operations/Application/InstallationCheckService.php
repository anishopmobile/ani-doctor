<?php

namespace App\Modules\Operations\Application;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InstallationCheckService
{
    /** Read-only installation diagnostics. Never return credentials, SQL or exception messages. */
    public function inspect(): array
    {
        $checks = [];
        $pending = [];
        $probe = function (string $name, callable $check) use (&$checks): bool {
            try {
                $passed = (bool) $check();
            } catch (\Throwable) {
                $passed = false;
            }
            $checks[$name] = $passed ? 'pass' : 'fail';

            return $passed;
        };
        $probe('app_key', function (): bool {
            $key = config('app.key');
            if (!is_string($key)) {
                return false;
            }
            $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

            return is_string($key) && Encrypter::supported($key, config('app.cipher'));
        });
        $probe('runtime_directories', function (): bool {
            foreach (['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
                if (!is_dir(base_path($directory)) || !is_writable(base_path($directory))) {
                    return false;
                }
            }

            return true;
        });
        $probe('http_local_configuration', function (): bool {
            if (parse_url(config('app.url'), PHP_URL_SCHEME) !== 'http') {
                return true;
            }

            return in_array(config('app.env'), ['local', 'testing'], true) && !config('session.secure');
        });
        if ($probe('database_connection', fn () => DB::select('SELECT 1'))) {
            $probe('schema_current', function () use (&$pending): bool {
                $required = array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php')));
                $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
                $pending = array_values(array_diff($required, $ran));

                return $pending === [];
            });
            $probe('website_schema', function (): bool {
                foreach (['website_sections', 'website_links', 'website_articles', 'website_taxonomies', 'website_article_taxonomy', 'website_media', 'website_theme_settings', 'website_service_contents'] as $table) {
                    if (!Schema::hasTable($table)) {
                        return false;
                    }
                }

                return Schema::hasColumn('website_theme_settings', 'brand_settings');
            });
        }

        return [
            'checks' => $checks,
            'pending_migrations' => $pending,
            'configuration_cached' => app()->configurationIsCached(),
            'ready' => !in_array('fail', $checks, true),
            'scope' => 'read_only_installation_check_not_production_certification',
        ];
    }
}
