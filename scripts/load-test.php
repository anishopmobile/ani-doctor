<?php

// Deliberately limited to loopback staging, below the unchanged 60/min calendar rate limit.
$options = getopt('', ['origin:', 'ca:', 'service:', 'date:', 'month:']);
$origin = rtrim($options['origin'] ?? '', '/');
$parts = parse_url($origin);
if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)
    || isset($parts['user']) || isset($parts['query']) || !is_file($options['ca'] ?? '')
    || !ctype_digit($options['service'] ?? '') || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $options['date'] ?? '')
    || !preg_match('/\A\d{4}-\d{2}\z/', $options['month'] ?? '')) {
    fwrite(STDERR, "Use a loopback HTTPS staging origin, explicit trusted CA and synthetic fixture selectors.\n");
    exit(1);
}
$day = $origin.'/api/v1/availability?'.http_build_query(['service_id' => $options['service'], 'date' => $options['date']]);
$month = $origin.'/api/v1/calendar?'.http_build_query(['service_id' => $options['service'], 'month' => $options['month']]);
$jobs = array_merge(array_fill(0, 40, ['day', $day]), array_fill(0, 8, ['month', $month]));
$multi = curl_multi_init();
$active = [];
$results = ['day' => [], 'month' => []];
$failures = 0;
$begin = microtime(true);
while ($jobs || $active) {
    while ($jobs && count($active) < 4) {
        [$kind, $url] = array_shift($jobs);
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CAINFO => $options['ca'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $active[spl_object_id($handle)] = [$kind, $handle];
        curl_multi_add_handle($multi, $handle);
    }
    do {
        $status = curl_multi_exec($multi, $running);
    } while ($status === CURLM_CALL_MULTI_PERFORM);
    while ($info = curl_multi_info_read($multi)) {
        $handle = $info['handle'];
        [$kind] = $active[spl_object_id($handle)];
        $response = json_decode(curl_multi_getcontent($handle), true);
        $valid = $info['result'] === CURLE_OK && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200 && ($response['success'] ?? false)
            && ($kind === 'day' ? isset($response['data']['slots']) : isset($response['data']['days']));
        $failures += $valid ? 0 : 1;
        $results[$kind][] = round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000, 2);
        curl_multi_remove_handle($multi, $handle);
        unset($active[spl_object_id($handle)]);
        curl_close($handle);
    }
    if ($active) {
        curl_multi_select($multi, 0.05);
    }
}
curl_multi_close($multi);
$summary = [];
foreach ($results as $kind => $times) {
    sort($times);
    $summary[$kind] = ['requests' => count($times), 'p50_ms' => $times[(int) ceil(count($times) * 0.5) - 1], 'p95_ms' => $times[(int) ceil(count($times) * 0.95) - 1], 'max_ms' => max($times)];
}
$passed = $failures === 0 && $summary['day']['p95_ms'] <= 1000;
echo json_encode(['concurrency' => 4, 'total_requests' => 48, 'failures' => $failures, 'duration_seconds' => round(microtime(true) - $begin, 2), 'latency' => $summary, 'day_p95_target_ms' => 1000, 'passed' => $passed, 'scope' => 'synthetic_loopback_only; not capacity certification'])."\n";
exit($passed ? 0 : 1);
