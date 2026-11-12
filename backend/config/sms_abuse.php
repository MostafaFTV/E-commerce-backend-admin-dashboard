<?php

return [
    // All OTP purposes share the allowance for each normalized phone.
    'per_phone_hour' => (int) env('TKB_OTP_PHONE_HOURLY_LIMIT', 6),
    'per_phone_day' => (int) env('TKB_OTP_PHONE_DAILY_LIMIT', 15),

    // Limits are per requesting IP across all OTP-sending routes.
    'per_ip_10_minutes' => 15,
    'per_ip_hour' => 60,
    'per_ip_day' => 200,

    // No global SMS budget/cap is applied.
];
