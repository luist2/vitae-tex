<?php

namespace App\Support\Database;

use LogicException;

final class PostgresTlsGuard
{
    /**
     * Refuse a production PostgreSQL connection that does not require TLS.
     */
    public static function assertSecure(string $environment, string $connection, mixed $sslMode): void
    {
        if ($environment !== 'production' || $connection !== 'pgsql') {
            return;
        }

        if (is_string($sslMode) && in_array($sslMode, ['require', 'verify-ca', 'verify-full'], true)) {
            return;
        }

        throw new LogicException(
            'Production PostgreSQL connections require DB_SSLMODE=require, verify-ca, or verify-full.',
        );
    }
}
