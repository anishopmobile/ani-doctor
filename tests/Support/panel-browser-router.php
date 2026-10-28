<?php

// Never in the host ZIP. This test-only router cannot target real clinic databases.
if (getenv('PANEL_SYNTHETIC_FIXTURE') !== '1' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403);
    exit;
}
$base = dirname(__DIR__, 2);
$public = realpath($base.'/public');
$file = realpath($public.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || !str_ends_with(config('database.connections.sqlite.database'), '-panel-ui.sqlite')
    || config('app.url') !== 'http://127.0.0.1:18558' || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    abort(403);
}
$mobile = match ($request->header('X-UI-Actor')) {
    'patient' => '09000000552', 'secretary' => '09000000554', 'guest' => null, default => '09000000551'
};
if ($mobile) {
    $app['auth']->onceUsingId(App\Modules\Identity\Infrastructure\User::where('mobile', $mobile)->sole()->id);
}
$app['router']->middleware('web')->get('/__ui-fixture', fn () => view()->file(__DIR__.'/panel-controls-fixture.blade.php'));
$app['router']->middleware('web')->post('/__ui-submit', fn () => response()->json($request->only(['kind', 'services', 'date', 'required_choice'])));
$app->handleRequest($request);
