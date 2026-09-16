<?php

return [
    'max_failed_attempts' => (int) env(
        'AUTH_MAX_FAILED_ATTEMPTS',
        5
    ),

    'lockout_minutes' => (int) env(
        'AUTH_LOCKOUT_MINUTES',
        15
    ),
];
