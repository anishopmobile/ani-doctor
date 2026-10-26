<?php

return [
    // Separate from synchronous OTP. Enable only after reviewing approved neutral templates.
    'enabled' => env('EVENT_SMS_ENABLED', false),
    'max_attempts' => 3,
];
