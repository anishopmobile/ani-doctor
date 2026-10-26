<?php

namespace App\Modules\Identity\Application;

final class LocalOtpPreview
{
    public function enabled(): bool
    {
        $request = request();
        $hosts = ['localhost', '127.0.0.1', '::1', '[::1]'];

        return app()->environment('local')
            && config('identity.local_otp_preview') === true
            && in_array(parse_url((string) config('app.url'), PHP_URL_HOST), $hosts, true)
            && in_array($request->getHost(), $hosts, true)
            // Do not let forwarded headers enable preview for a remote client.
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }
}
