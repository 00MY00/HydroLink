<?php

/*
 * Default values of HydroLink. No personal data here: everything specific to an instance is saved in FreeScout's
 * options table (Manage > HydroLink) or in storage/app/hydrolink/.
 */
return [
    // where the hydroxide binaries, hydroxide-version.txt and SHA256SUMS are published (CI of the HydroLink repository)
    'release_url' => env('HYDROLINK_RELEASE_URL', 'https://github.com/00MY00/HydroLink/releases/latest/download'),

    // local ports of hydroxide (above 1024, 127.0.0.1 only)
    'imap_port' => 1143,
    'smtp_port' => 1025,

    // watchdog: at most this many starts in the window, then it stops trying (HL-SVC-01)
    'max_starts'        => 5,
    'max_starts_window' => 600,

    // health checks and binary checks (seconds)
    'health_interval'       => 300,
    'release_interval'      => 21600,
    'fetch_stale_after'     => 1800,

    // authentication: seconds a person has to give a 2FA code or solve a verification, and request lifetime
    'auth_wait_timeout' => 600,
    'auth_request_ttl'  => 900,

    // keep-alive: accounts handled per cron run, delay before a new try after an attempt (seconds)
    'keepalive_per_tick' => 3,
    'keepalive_retry'    => 3600,

    // VirusTotal public API: 4 requests per minute, 500 per day
    'vt_per_minute' => 4,
    'vt_per_day'    => 500,

    // notifications: reminder of an unsolved problem (hours, 0 = never)
    'reminder_hours' => 24,
];
