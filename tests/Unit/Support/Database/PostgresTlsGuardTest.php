<?php

namespace Tests\Unit\Support\Database;

use App\Support\Database\PostgresTlsGuard;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostgresTlsGuardTest extends TestCase
{
    #[DataProvider('secureModes')]
    public function test_it_accepts_tls_modes_for_production_postgresql(string $sslMode): void
    {
        PostgresTlsGuard::assertSecure('production', 'pgsql', $sslMode);

        $this->addToAssertionCount(1);
    }

    public static function secureModes(): array
    {
        return [
            'encrypted without certificate verification' => ['require'],
            'verified certificate authority' => ['verify-ca'],
            'verified certificate and hostname' => ['verify-full'],
        ];
    }

    #[DataProvider('insecureModes')]
    public function test_it_rejects_non_tls_modes_for_production_postgresql(mixed $sslMode): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production PostgreSQL connections require DB_SSLMODE');

        PostgresTlsGuard::assertSecure('production', 'pgsql', $sslMode);
    }

    public static function insecureModes(): array
    {
        return [
            'disabled' => ['disable'],
            'preferred only' => ['prefer'],
            'allowed only' => ['allow'],
            'missing' => [null],
        ];
    }

    public function test_it_does_not_restrict_local_postgresql_or_other_production_connections(): void
    {
        PostgresTlsGuard::assertSecure('local', 'pgsql', 'disable');
        PostgresTlsGuard::assertSecure('production', 'sqlite', null);

        $this->addToAssertionCount(2);
    }
}
