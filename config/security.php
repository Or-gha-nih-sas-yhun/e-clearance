<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    'hsts' => (bool) env('SECURITY_HSTS', env('APP_ENV') === 'production'),

    'web_rate_limit' => (int) env('WEB_RATE_LIMIT_PER_MINUTE', 100),

    'content_security_policy' => env('CONTENT_SECURITY_POLICY', implode(' ', [
        "default-src 'self';",
        "base-uri 'self';",
        "object-src 'none';",
        "frame-ancestors 'self';",
        "form-action 'self';",
        "img-src 'self' data: blob:;",
        "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net https://cdn.jsdelivr.net;",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdn.jsdelivr.net;",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;",
        "connect-src 'self';",
        "frame-src 'self' blob:;",
        "media-src 'self' blob:;",
        "worker-src 'self' blob:;",
    ])),
];
