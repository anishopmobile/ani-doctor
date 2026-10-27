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
        $session->setId(Illuminate\Support\Str::random(40));
        $session->start();
        $session->flush();
        $session->put('login_web_'.sha1(Illuminate\Auth\SessionGuard::class), $user);
        $token = Illuminate\Support\Str::random(40);
        $session->put('_token', $token);
        $session->save();
        $name = config('session.cookie');
        $prefix = Illuminate\Cookie\CookieValuePrefix::create($name, app('encrypter')->getKey());
        $cookie = $name.'='.rawurlencode(app('encrypter')->encrypt($prefix.$session->getId(), false));

        return ['cookie' => $cookie, 'token' => $token];
    };
    $probe = static function (string $name, string $method, string $path, int $expected, ?array $session = null, ?array $payload = null, bool $csrf = true) use (&$checks): string {
        $curl = curl_init('https://localhost:18443'.$path);
        $headers = ['Accept: application/json'];
        if ($csrf && $session) {
            $headers[] = 'X-CSRF-TOKEN: '.$session['token'];
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CAINFO => '/etc/ssl/certs/p8.crt', CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false]);
        if ($session) {
            curl_setopt($curl, CURLOPT_COOKIE, $session['cookie']);
        }
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
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
    echo json_encode(['passed' => true, 'scope' => 'synthetic_https_real_session_csrf_no_test_bypass', 'checks' => $checks], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    echo json_encode(['passed' => false, 'checks' => $checks], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}
