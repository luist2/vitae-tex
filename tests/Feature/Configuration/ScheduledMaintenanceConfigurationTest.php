<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class ScheduledMaintenanceConfigurationTest extends TestCase
{
    public function test_the_workflow_runs_only_the_password_reset_cleanup_daily_and_on_demand(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression("/^  schedule:\n    - cron: '17 3 \* \* \*'$/m", $workflow);
        $this->assertMatchesRegularExpression('/^  workflow_dispatch:$/m', $workflow);
        $this->assertMatchesRegularExpression('/^  contents: read$/m', $workflow);
        $this->assertMatchesRegularExpression('/^  cancel-in-progress: false$/m', $workflow);
        $this->assertMatchesRegularExpression('/^    timeout-minutes: 10$/m', $workflow);
        $this->assertStringContainsString('php artisan auth:clear-resets --no-interaction', $workflow);
        $this->assertSame(1, substr_count($workflow, 'auth:clear-resets'));
        $this->assertStringNotContainsString('schedule:run', $workflow);
        $this->assertStringNotContainsString('cv:prune-pdf-temporaries', $workflow);
        $this->assertStringNotContainsString('artisan migrate', $workflow);
    }

    public function test_the_workflow_uses_only_the_tls_maintenance_connection_secret_and_an_ephemeral_app_key(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString('DB_CONNECTION: pgsql', $workflow);
        $this->assertStringContainsString('DB_URL: ${{ secrets.NEON_MAINTENANCE_DATABASE_URL }}', $workflow);
        $this->assertStringContainsString('DB_SSLMODE: require', $workflow);
        $this->assertStringContainsString('APP_ENV: production', $workflow);
        $this->assertStringContainsString('base64_encode(random_bytes(32))', $workflow);
        $this->assertStringContainsString("printf 'APP_KEY=%s\\n' \"\$ephemeral_key\" >> \"\$GITHUB_ENV\"", $workflow);
        $this->assertStringNotContainsString('secrets.APP_KEY', $workflow);
        $this->assertDoesNotMatchRegularExpression('/^\s+APP_KEY:\s+/m', $workflow);
        $this->assertStringNotContainsString('NEON_MIGRATION_DATABASE_URL', $workflow);
        $this->assertStringNotContainsString('NEON_RUNTIME_DATABASE_URL', $workflow);
        $this->assertStringNotContainsString('neondb_owner', $workflow);
        $this->assertStringNotContainsString('vitaetex_app', $workflow);
        $this->assertDoesNotMatchRegularExpression('/postgres(?:ql)?:\/\/[^\s"\x27]*:[^@\s"\x27]+@/', $workflow);
    }

    public function test_the_maintenance_role_is_limited_to_deleting_expired_tokens(): void
    {
        $configuration = file_get_contents(base_path('database/operations/configure-neon-maintenance-role.sql'));
        $verification = file_get_contents(base_path('database/operations/verify-neon-maintenance-privileges.sql'));

        $this->assertIsString($configuration);
        $this->assertIsString($verification);
        $this->assertStringContainsString('LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS', $configuration);
        $this->assertStringContainsString('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public', $configuration);
        $this->assertStringContainsString('GRANT DELETE ON TABLE public.password_reset_tokens', $configuration);
        $this->assertStringContainsString('GRANT SELECT (created_at) ON TABLE public.password_reset_tokens', $configuration);
        $this->assertStringNotContainsString('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES', $configuration);
        $this->assertStringNotContainsString('GRANT USAGE, SELECT ON ALL SEQUENCES', $configuration);
        $this->assertStringContainsString("NOT has_column_privilege(current_user, 'public.password_reset_tokens', 'email', 'SELECT')", $verification);
        $this->assertStringContainsString("NOT has_column_privilege(current_user, 'public.password_reset_tokens', 'token', 'SELECT')", $verification);
        $this->assertStringContainsString('other_tables_are_inaccessible', $verification);
        $this->assertStringContainsString('sequences_are_inaccessible', $verification);
    }

    public function test_the_maintenance_scripts_require_direct_tls_connections_without_storing_secrets(): void
    {
        $configuration = file_get_contents(base_path('scripts/configure-neon-maintenance-role.sh'));
        $verification = file_get_contents(base_path('scripts/verify-neon-maintenance-role.sh'));

        $this->assertIsString($configuration);
        $this->assertIsString($verification);
        $this->assertStringContainsString('NEON_MIGRATION_DATABASE_URL', $configuration);
        $this->assertStringContainsString('NEON_MAINTENANCE_DATABASE_URL', $verification);
        $this->assertStringContainsString('*-pooler.*', $configuration);
        $this->assertStringContainsString('*-pooler.*', $verification);
        $this->assertStringContainsString('sslmode=require', $configuration);
        $this->assertStringContainsString('sslmode=require', $verification);
        $this->assertStringContainsString('stty -echo', $configuration);
        $this->assertStringNotContainsString('--env NEON_MAINTENANCE_PASSWORD', $configuration);

        foreach ([$configuration, $verification] as $contents) {
            $this->assertDoesNotMatchRegularExpression('/postgres(?:ql)?:\/\/[^\s"\x27]*:[^@\s"\x27]+@/', $contents);
            $this->assertStringNotContainsString('.neon.tech/', $contents);
        }
    }

    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/clear-expired-password-resets.yml'));

        $this->assertIsString($workflow);

        return $workflow;
    }
}
