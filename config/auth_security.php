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

    // Horas que sirve una contrasena temporal desde que se emite.
    'horas_temporal' => (int) env(
        'AUTH_HORAS_TEMPORAL',
        72
    ),
];
