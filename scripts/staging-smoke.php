<?php

$options = getopt('', ['origin:', 'ca:']);
$origin = rtrim($options['origin'] ?? '', '/');
$url = parse_url($origin);
if (($url['scheme'] ?? '') !== 'https' || !in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true) || !is_file($options['ca'] ?? '')) {
    fwrite(STDERR, "Loopback HTTPS and explicit staging CA required.\n");
    exit(1);
}
$checks = [];
$cases = [['login', '/login', [200]], ['health', '/up', [200]], ['finance_auth', '/admin/finance', [302]], ['reports_auth', '/management/reports', [302]], ['environment', '/.env', [403, 404]], ['vendor', '/vendor/autoload.php', [403, 404]], ['runtime', '/storage/logs/laravel.log', [403, 404]], ['config', '/bootstrap/cache/config.php', [403, 404]], ['source', '/composer.lock', [403, 404]], ['host', '/up', [421], 'host'], ['csrf', '/otp/request', [419], 'post']];
foreach ($cases as $case) {
    [$name, $path, $expected] = $case;
    $handle = curl_init($origin.$path);
    $headers = [];
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CAINFO => $options['ca'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
        return strlen($line);
    }]);
    if (($case[3] ?? '') === 'host') {
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Host: attacker.example.invalid']);
    }
    if (($case[3] ?? '') === 'post') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, 'mobile=09000000008');
    }
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $passed = $body !== false && in_array($status, $expected, true);
    if (in_array($name, ['login', 'health', 'host', 'csrf'], true)) {
        $passed = $passed && ($headers['x-content-type-options'] ?? '') === 'nosniff'
            && str_contains($headers['cache-control'] ?? '', 'no-store')
            && !isset($headers['x-powered-by']);
    }
    $checks[$name] = ['status' => $status, 'passed' => $passed];
    curl_close($handle);
}
$passed = !array_filter($checks, fn ($check) => !$check['passed']);
echo json_encode(['passed' => $passed, 'checks' => $checks, 'scope' => 'loopback_installation_only']).PHP_EOL;
exit($passed ? 0 : 1);
