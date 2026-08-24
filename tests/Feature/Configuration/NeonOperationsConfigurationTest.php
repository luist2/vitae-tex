<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class NeonOperationsConfigurationTest extends TestCase
{
    public function test_the_migration_runner_requires_a_direct_tls_connection(): void
    {
        $script = file_get_contents(base_path('scripts/run-neon-migrations.sh'));

        $this->assertIsString($script);
        $this->assertStringContainsString('NEON_MIGRATION_DATABASE_URL', $script);
        $this->assertStringContainsString('*-pooler.*', $script);
        $this->assertStringContainsString('sslmode=require', $script);
        $this->assertStringContainsString('artisan migrate --force', $script);
        $this->assertStringNotContainsString('postgresql://neondb_owner:', $script);
    }

    public function test_the_production_image_has_a_secure_database_default_without_applying_runtime_guards_during_build(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString('ENV APP_ENV=build', $dockerfile);
        $this->assertStringContainsString('DB_SSLMODE=require', $dockerfile);
    }

    public function test_the_runtime_privilege_contract_excludes_schema_and_migration_ownership(): void
    {
        $createSql = file_get_contents(base_path('database/operations/create-neon-runtime-role.sql'));
        $grantSql = file_get_contents(base_path('database/operations/grant-neon-runtime-privileges.sql'));
        $verifySql = file_get_contents(base_path('database/operations/verify-neon-runtime-privileges.sql'));
        $configureScript = file_get_contents(base_path('scripts/configure-neon-runtime-role.sh'));

        $this->assertIsString($createSql);
        $this->assertIsString($grantSql);
        $this->assertIsString($verifySql);
        $this->assertIsString($configureScript);
        $this->assertStringContainsString('AND NOT rolsuper', $createSql);
        $this->assertStringContainsString('AND NOT rolinherit', $createSql);
        $this->assertStringContainsString('\\getenv runtime_password NEON_RUNTIME_PASSWORD', $createSql);
        $this->assertStringContainsString('ALTER ROLE :"runtime_role" PASSWORD :\'runtime_password\';', $createSql);
        $this->assertStringNotContainsString('\\password', $createSql);
        $this->assertStringNotContainsString('ALTER ROLE :"runtime_role" NOSUPERUSER', $createSql);
        $this->assertStringContainsString('stty -echo', $configureScript);
        $this->assertStringContainsString('Las contraseñas no coinciden.', $configureScript);
        $this->assertStringNotContainsString('--env NEON_RUNTIME_PASSWORD', $configureScript);
        $this->assertStringContainsString('GRANT CONNECT ON DATABASE', $grantSql);
        $this->assertStringContainsString('GRANT USAGE ON SCHEMA public', $grantSql);
        $this->assertStringContainsString('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES', $grantSql);
        $this->assertStringContainsString('REVOKE ALL PRIVILEGES ON TABLE public.migrations', $grantSql);
        $this->assertStringNotContainsString('GRANT CREATE', $grantSql);
        $this->assertStringContainsString("granted_role.rolname = 'neon_superuser'", $verifySql);
        $this->assertStringContainsString("NOT has_schema_privilege(current_user, 'public', 'CREATE')", $verifySql);
    }

    public function test_no_neon_operation_file_contains_a_connection_string(): void
    {
        $paths = array_merge(
            glob(base_path('database/operations/*')) ?: [],
            glob(base_path('scripts/*neon*')) ?: [],
        );

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression('/postgres(?:ql)?:\/\/[^\s"\x27]*:[^@\s"\x27]+@/', $contents, $path);
            $this->assertStringNotContainsString('.neon.tech/', $contents, $path);
        }
    }
}
