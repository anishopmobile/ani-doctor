<?php

require '/var/www/app/vendor/autoload.php';
$app = require '/var/www/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('P8_SYNTHETIC_FIXTURE') !== '1' || config('database.connections.mysql.database') !== 'ani_doctor_p8_smoke'
    || config('app.url') !== 'https://localhost:18443' || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    throw new RuntimeException('ISOLATED_SECURITY_FIXTURE_REQUIRED');
}
$checks = [];
try {
    $sessionFor = static function (int $user): array {
        $session = app('session')->driver();
        // Reset the database handler's existence state as well as the ID.
        $session->migrate(false);
        $session->start();
        $session->flush();
        $session->put('login_web_'.sha1(Illuminate\Auth\SessionGuard::class), $user);
        $token = Illuminate\Support\Str::random(40);
        $session->put('_token', $token);
        $session->save();
        if (!Illuminate\Support\Facades\DB::table(config('session.table'))->where('id', $session->getId())->exists()) {
            throw new RuntimeException('SYNTHETIC_SESSION_NOT_PERSISTED');
        }
        $name = config('session.cookie');
        $prefix = Illuminate\Cookie\CookieValuePrefix::create($name, app('encrypter')->getKey());
        $cookie = $name.'='.rawurlencode(app('encrypter')->encrypt($prefix.$session->getId(), false));

        return ['cookie' => $cookie, 'token' => $token];
    };
    $probe = static function (string $name, string $method, string $path, int $expected, ?array $session = null, ?array $payload = null, bool $csrf = true, bool $multipart = false) use (&$checks): string {
        $curl = curl_init('https://localhost:18443'.$path);
        $headers = ['Accept: application/json'];
        if ($csrf && $session) {
            $headers[] = 'X-CSRF-TOKEN: '.$session['token'];
        }
        if ($payload !== null && !$multipart) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CAINFO => '/etc/ssl/certs/p8.crt', CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false]);
        if ($session) {
            curl_setopt($curl, CURLOPT_COOKIE, $session['cookie']);
        }
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $payload : json_encode($payload, JSON_THROW_ON_ERROR));
        }
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $checks[$name] = ['status' => $status, 'passed' => $body !== false && $status === $expected];
        if (!$checks[$name]['passed']) {
            throw new RuntimeException('HTTP_SECURITY_PROBE_FAILED');
        }

        return $body;
    };
    $admin = App\Modules\Identity\Infrastructure\User::where('mobile', '09000000008')->firstOrFail();
    $patient = App\Modules\Identity\Infrastructure\User::create(['mobile' => '09000000445']);
    $patient->forceFill(['mobile_verified_at' => now()])->save();
    $editor = $sessionFor($admin->id);
    $ordinary = $sessionFor($patient->id);
    $page = App\Modules\Website\Infrastructure\WebsitePage::where('slug', 'security-audit')->firstOrFail();
    $base = '/admin/website-pages/'.$page->id;
    $probe('guest_editor_denied', 'GET', $base.'/edit', 401);
    $probe('patient_editor_denied', 'GET', $base.'/edit', 403, $ordinary);
    $probe('patient_preview_denied', 'GET', $base.'/preview', 403, $ordinary);
    $probe('admin_editor_allowed', 'GET', $base.'/edit', 200, $editor);
    $probe('admin_preview_allowed', 'GET', $base.'/preview', 200, $editor);
    $probe('csrf_required', 'POST', $base.'/publish', 419, $editor, ['expected_version' => 3], false);
    $probe('patient_publish_denied', 'POST', $base.'/publish', 403, $ordinary, ['expected_version' => 3]);
    $bad = $page->blocks;
    $bad[0]['button_label'] = 'payload';
    $bad[0]['button_url'] = 'javascript:alert(1)';
    $probe('executable_url_denied', 'PUT', $base, 422, $editor, ['title' => 'bad', 'blocks' => $bad, 'expected_version' => 3]);
    $probe('draft_saved', 'PUT', $base, 200, $editor, ['title' => 'PRIVATE_DRAFT_SENTINEL', 'blocks' => $page->blocks, 'expected_version' => 3]);
    $public = $probe('public_snapshot_available', 'GET', '/pages/security-audit', 200);
    $checks['draft_not_disclosed'] = ['passed' => !str_contains($public, 'PRIVATE_DRAFT_SENTINEL')];
    if (!$checks['draft_not_disclosed']['passed']) {
        throw new RuntimeException('DRAFT_DISCLOSED');
    }
    $probe('stale_publication_denied', 'POST', $base.'/publish', 422, $editor, ['expected_version' => 3]);
    $probe('stale_deletion_denied', 'DELETE', $base, 422, $editor, ['expected_version' => 3]);
    $probe('private_image_path_denied', 'GET', '/storage/app/private/website-media/probe.png', 404);
    $themeValues = App\Modules\Website\Application\ThemeService::DEFAULTS + App\Modules\Website\Application\ThemeService::EXTRA_COLORS;
    $probe('patient_theme_denied', 'GET', '/admin/website-theme', 403, $ordinary);
    $probe('guest_theme_preview_css_denied', 'GET', '/admin/website-theme/preview.css', 401);
    $probe('admin_theme_allowed', 'GET', '/admin/website-theme', 200, $editor);
    $probe('theme_saved', 'PUT', '/admin/website-theme', 302, $editor, $themeValues);
    $probe('theme_preview_csrf_required', 'PUT', '/admin/website-theme/preview', 419, $editor, $themeValues, false);
    $probe('patient_theme_preview_denied', 'PUT', '/admin/website-theme/preview', 403, $ordinary, $themeValues);
    $probe('theme_preview_allowed', 'PUT', '/admin/website-theme/preview', 200, $editor, array_replace($themeValues, ['hero_title' => 'THEME_PRIVATE_PREVIEW_SENTINEL', 'primary_color' => '#112233']));
    $previewCss = $probe('theme_preview_css_allowed', 'GET', '/admin/website-theme/preview.css', 200, $editor);
    $publicCss = $probe('theme_public_css_unchanged', 'GET', '/website-theme.css', 200);
    $publicHome = $probe('theme_home_unchanged', 'GET', '/', 200);
    $checks['theme_preview_does_not_publish'] = ['passed' => str_contains($previewCss, '#112233') && !str_contains($publicCss, '#112233') && !str_contains($publicHome, 'THEME_PRIVATE_PREVIEW_SENTINEL')];
    if (!$checks['theme_preview_does_not_publish']['passed']) {
        throw new RuntimeException('THEME_PREVIEW_DISCLOSED');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'cms-probe-');
    try {
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $temporary);
        $probe(
            'real_multipart_image_upload',
            'POST',
            $base.'/assets',
            302,
            $editor,
            ['image' => new CURLFile($temporary, 'image/png', 'probe.png'), 'alt_text' => 'تصویر ساختگی ارزیابی'],
            true,
            true
        );
        $asset = $page->assets()->with('media')->sole();
        $imagePath = '/website-media/'.$asset->media->id;
        $probe('editor_image_preview', 'GET', $imagePath, 200, $editor);
        $probe('unpublished_image_denied', 'GET', $imagePath, 404);
        $blocks = $page->fresh()->blocks;
        $blocks[0]['asset_id'] = $asset->id;
        $probe('image_draft_saved', 'PUT', $base, 200, $editor, ['title' => $page->title, 'blocks' => $blocks, 'expected_version' => 4]);
        $probe('draft_image_still_private', 'GET', $imagePath, 404);
        $probe('publish_image_snapshot', 'POST', $base.'/publish', 200, $editor, ['expected_version' => 5]);
        $probe('published_image_available', 'GET', $imagePath, 200);
        $probe('patient_portrait_upload_denied', 'POST', '/admin/website-media/portrait/1', 403, $ordinary, []);
        $upload = ['image' => new CURLFile($temporary, 'image/png', 'portrait.png'), 'alt_text' => 'تصویر ساختگی هویت'];
        $probe('portrait_uploaded', 'POST', '/admin/website-media/portrait/1', 302, $editor, $upload, true, true);
        $portrait = App\Modules\Website\Infrastructure\WebsiteMedia::where('owner_type', 'portrait')->where('owner_id', 1)->sole();
        $portraitPath = '/website-media/'.$portrait->id;
        $probe('portrait_private_preview', 'GET', $portraitPath, 200, $editor);
        $probe('portrait_unpublished_denied', 'GET', $portraitPath, 404);
        $version = app(App\Modules\Website\Application\ThemeService::class)->settings()['settings_version'];
        $probe('portrait_explicit_publication', 'PUT', '/admin/website-theme', 302, $editor, $themeValues + ['portrait_is_published' => 1, 'expected_settings_version' => $version]);
        $probe('portrait_public_read', 'GET', $portraitPath, 200);
        $probe('portrait_referenced_delete_denied', 'DELETE', '/admin/website-media/'.$portrait->id, 422, $editor);
        $probe('theme_stale_write_denied', 'PUT', '/admin/website-theme', 422, $editor, $themeValues + ['expected_settings_version' => $version]);
        $probe('portrait_replacement', 'POST', '/admin/website-media/portrait/1', 302, $editor, $upload, true, true);
        $probe('portrait_replacement_private', 'GET', $portraitPath, 404);
        $probe('portrait_unpublished_delete', 'DELETE', '/admin/website-media/'.$portrait->id, 302, $editor);
        file_put_contents($temporary, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $probe(
            'svg_upload_denied',
            'POST',
            $base.'/assets',
            422,
            $editor,
            ['image' => new CURLFile($temporary, 'image/svg+xml', 'payload.svg'), 'alt_text' => 'آزمون رد فایل'],
            true,
            true
        );
        if ($page->assets()->count() !== 1) {
            throw new RuntimeException('INVALID_UPLOAD_LEFT_ASSET');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
    echo json_encode(['passed' => true, 'scope' => 'synthetic_https_real_session_csrf_no_test_bypass', 'checks' => $checks], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    echo json_encode(['passed' => false, 'checks' => $checks], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}
