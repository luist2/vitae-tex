<?php

namespace Tests\Feature\Configuration;

use Illuminate\Mail\MailManager;
use LogicException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class BrevoMailConfigurationTest extends TestCase
{
    public function test_brevo_uses_the_https_api_transport_without_exposing_its_key(): void
    {
        config(['services.brevo.key' => 'test-brevo-api-key']);

        $manager = app(MailManager::class);
        $manager->purge('brevo');

        $transport = $manager->mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
        $this->assertSame('brevo+api://api.brevo.com', (string) $transport);
        $this->assertStringNotContainsString('test-brevo-api-key', (string) $transport);
    }

    public function test_brevo_rejects_a_missing_api_key_before_sending(): void
    {
        config(['services.brevo.key' => null]);

        $manager = app(MailManager::class);
        $manager->purge('brevo');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('La API key de Brevo no está configurada.');

        $manager->mailer('brevo');
    }
}
