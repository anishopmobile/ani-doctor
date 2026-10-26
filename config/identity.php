<?php

return [
    'local_otp_preview' => env('LOCAL_OTP_PREVIEW', false),
    'otp_ttl_seconds' => 120,
    'resend_seconds' => 60,
    'max_attempts' => 5,
    'guest_verification_seconds' => 600,
    'display_timezone' => 'Asia/Tehran',
    'sms_ir' => [
        'api_key' => env('SMS_IR_API_KEY'),
        'otp_template_id' => env('SMS_IR_OTP_TEMPLATE_ID'),
        'otp_parameter' => env('SMS_IR_OTP_PARAMETER', 'CODE'),
    ],
];
