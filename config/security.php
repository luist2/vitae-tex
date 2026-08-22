<?php

$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);
$trustedHosts = (string) env('TRUSTED_HOSTS', is_string($appHost) ? $appHost : '');
$isProduction = env('APP_ENV', 'production') === 'production';

return [
    'trusted_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => trim($host),
        explode(',', $trustedHosts),
    ))),

    'content_security_policy' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', $isProduction),
    ],

    'strict_transport_security' => [
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', $isProduction ? 31536000 : 0),
    ],
];
