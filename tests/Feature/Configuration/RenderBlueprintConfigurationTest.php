<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class RenderBlueprintConfigurationTest extends TestCase
{
    public function test_the_blueprint_defines_only_the_free_docker_web_service(): void
    {
        $blueprint = $this->blueprint();

        $this->assertSame(1, preg_match_all('/^\s+- type:/m', $blueprint));
        $this->assertMatchesRegularExpression('/^\s+- type: web$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+name: vitaetex$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+runtime: docker$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+plan: free$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+region: ohio$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+branch: main$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+dockerfilePath: \.\/Dockerfile$/m', $blueprint);
        $this->assertMatchesRegularExpression('/^\s+healthCheckPath: \/up$/m', $blueprint);
        $this->assertStringNotContainsString('type: worker', $blueprint);
        $this->assertStringNotContainsString('type: cron', $blueprint);
        $this->assertStringNotContainsString('preDeployCommand:', $blueprint);
        $this->assertStringNotContainsString('dockerCommand:', $blueprint);
    }

    public function test_the_blueprint_declares_the_secure_runtime_environment_contract(): void
    {
        $environment = $this->environment();

        $this->assertSame([
            'APP_NAME' => 'VitaeTex',
            'APP_ENV' => 'production',
            'APP_KEY' => false,
            'APP_DEBUG' => 'false',
            'APP_URL' => false,
            'APP_LOCALE' => 'es',
            'APP_FALLBACK_LOCALE' => 'es',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => false,
            'DB_SSLMODE' => 'require',
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'QUEUE_FAILED_DRIVER' => 'null',
            'FILESYSTEM_DISK' => 'local',
            'MAIL_MAILER' => 'brevo',
            'MAIL_FROM_ADDRESS' => 'vitaetex.app@gmail.com',
            'MAIL_FROM_NAME' => 'VitaeTex',
            'BREVO_API_KEY' => false,
            'BREVO_TIMEOUT_SECONDS' => '10',
            'TRUSTED_HOSTS' => false,
            'TRUSTED_PROXIES' => '*',
            'SECURITY_CSP_ENABLED' => 'true',
            'SECURITY_HSTS_MAX_AGE' => '31536000',
            'PRIVACY_CONTACT_EMAIL' => false,
        ], $environment);
    }

    public function test_the_blueprint_contains_no_credentials_or_administrative_database_role(): void
    {
        $blueprint = $this->blueprint();

        $this->assertDoesNotMatchRegularExpression('/postgres(?:ql)?:\/\/[^\s"\x27]*:[^@\s"\x27]+@/', $blueprint);
        $this->assertStringNotContainsString('.neon.tech/', $blueprint);
        $this->assertStringNotContainsString('neondb_owner', $blueprint);
        $this->assertStringNotContainsString('xkeysib-', $blueprint);
        $this->assertStringNotContainsString('MAIL_MAILER: log', $blueprint);
        $this->assertStringNotContainsString('MAIL_MAILER: array', $blueprint);
        $this->assertFalse($this->environment()['APP_KEY']);
        $this->assertFalse($this->environment()['DB_URL']);
        $this->assertFalse($this->environment()['BREVO_API_KEY']);
    }

    /**
     * @return array<string, string|false>
     */
    private function environment(): array
    {
        $lines = preg_split('/\R/', $this->blueprint());
        $environment = [];
        $keys = [];

        $this->assertIsArray($lines);

        foreach ($lines as $index => $line) {
            if (! preg_match('/^\s+- key: ([A-Z0-9_]+)$/', $line, $matches)) {
                continue;
            }

            $this->assertNotContains($matches[1], $keys, "Duplicate environment key {$matches[1]}.");
            $keys[] = $matches[1];
            $setting = $lines[$index + 1] ?? '';

            if (preg_match('/^\s+sync: false$/', $setting)) {
                $environment[$matches[1]] = false;

                continue;
            }

            $this->assertMatchesRegularExpression('/^\s+value: .+$/', $setting, "Missing value for {$matches[1]}.");
            $value = preg_replace('/^\s+value: /', '', $setting);

            $this->assertIsString($value);
            $environment[$matches[1]] = trim($value, "'\"");
        }

        return $environment;
    }

    private function blueprint(): string
    {
        $blueprint = file_get_contents(base_path('render.yaml'));

        $this->assertIsString($blueprint);

        return $blueprint;
    }
}
