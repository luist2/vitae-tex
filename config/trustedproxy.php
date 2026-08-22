<?php

$proxies = (string) env('TRUSTED_PROXIES', '');
$proxies = trim($proxies);

return [
    'proxies' => $proxies === '*'
        ? '*'
        : array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', $proxies),
        ))),
];
