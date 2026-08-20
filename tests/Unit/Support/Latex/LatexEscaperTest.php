<?php

namespace Tests\Unit\Support\Latex;

use App\Support\Latex\LatexEscaper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LatexEscaperTest extends TestCase
{
    private LatexEscaper $escaper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->escaper = new LatexEscaper;
    }

    public function test_text_escapes_every_latex_special_character_in_one_pass(): void
    {
        $this->assertSame(
            '\\textbackslash{}\\{\\}\\#\\$\\%\\&\\_\\textasciicircum{}\\textasciitilde{}',
            $this->escaper->text('\\{}#$%&_^~'),
        );
    }

    public function test_text_preserves_unicode_and_normalizes_inline_whitespace(): void
    {
        $this->assertSame(
            'María José Ñandú – 東京 línea dos con tabulación',
            $this->escaper->text("María José Ñandú – 東京\r\nlínea dos\tcon tabulación"),
        );
    }

    public function test_multiline_text_preserves_line_structure_after_escaping_each_line(): void
    {
        $this->assertSame(
            "Primera \\& segunda\n\nRuta \\textbackslash{}privada",
            $this->escaper->multiline("Primera & segunda\r\n\rRuta \\privada"),
        );
    }

    public function test_list_items_cannot_create_latex_list_structure_with_line_breaks(): void
    {
        $this->assertSame(
            'Primera \\textbackslash{}item\\{inyectado\\}',
            $this->escaper->listItem("Primera\n\\item{inyectado}"),
        );
    }

    #[DataProvider('hostileLatexTextProvider')]
    public function test_latex_like_payloads_remain_plain_text(string $payload, string $expected): void
    {
        $this->assertSame($expected, $this->escaper->text($payload));
    }

    /** @return array<string, array{string, string}> */
    public static function hostileLatexTextProvider(): array
    {
        return [
            'input command' => [
                '\\input{/etc/passwd}',
                '\\textbackslash{}input\\{/etc/passwd\\}',
            ],
            'unbalanced braces' => [
                'texto } \\end{document}',
                'texto \\} \\textbackslash{}end\\{document\\}',
            ],
            'shell escape attempt' => [
                '\\immediate\\write18{touch /tmp/x}',
                '\\textbackslash{}immediate\\textbackslash{}write18\\{touch /tmp/x\\}',
            ],
            'comment and math attempt' => [
                '% ocultar $contenido$ & columna',
                '\\% ocultar \\$contenido\\$ \\& columna',
            ],
        ];
    }

    #[DataProvider('invalidTextProvider')]
    public function test_text_contexts_reject_disallowed_controls(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres de control');

        $this->escaper->multiline($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidTextProvider(): array
    {
        return [
            'null byte' => ["texto\0oculto"],
            'escape control' => ["texto\x1Boculto"],
            'delete control' => ["texto\x7Foculto"],
            'unicode c1 control' => ["texto\u{0085}oculto"],
        ];
    }

    public function test_text_rejects_invalid_utf8(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('codificación inválida');

        $this->escaper->text("\xC3\x28");
    }

    #[DataProvider('validUrlProvider')]
    public function test_urls_are_validated_and_escaped_for_latex_destinations(string $url, string $expected): void
    {
        $this->assertSame($expected, $this->escaper->url($url));
    }

    /** @return array<string, array{string, string}> */
    public static function validUrlProvider(): array
    {
        return [
            'https with query and fragment' => [
                'https://example.com/a_b?x=1&y=2#fragment',
                'https://example.com/a\\_b?x=1\\&y=2\\#fragment',
            ],
            'http with encoded path' => [
                'http://example.com/perfil%20profesional',
                'http://example.com/perfil\\%20profesional',
            ],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_urls_reject_unsafe_or_ambiguous_destinations(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->escaper->url($url);
    }

    /** @return array<string, array{string}> */
    public static function invalidUrlProvider(): array
    {
        return [
            'unsupported scheme' => ['ftp://example.com/file'],
            'javascript scheme' => ['javascript:alert(1)'],
            'relative destination' => ['/perfil'],
            'missing host' => ['https:///perfil'],
            'opening brace' => ['https://example.com/{payload'],
            'closing brace' => ['https://example.com/payload}'],
            'backslash' => ['https://example.com/\\input'],
            'line break' => ["https://example.com/\npayload"],
            'surrounding whitespace' => [' https://example.com/perfil '],
            'control character' => ["https://example.com/\x1Bpayload"],
        ];
    }

    public function test_email_is_validated_and_escaped_for_a_mailto_destination(): void
    {
        $this->assertSame(
            'ada\\_lovelace+cv@example.com',
            $this->escaper->email('ada_lovelace+cv@example.com'),
        );
    }

    #[DataProvider('invalidEmailProvider')]
    public function test_email_rejects_invalid_or_unsafe_destinations(string $email): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->escaper->email($email);
    }

    /** @return array<string, array{string}> */
    public static function invalidEmailProvider(): array
    {
        return [
            'missing domain' => ['ada@'],
            'surrounding whitespace' => [' ada@example.com '],
            'brace' => ['ada{payload}@example.com'],
            'backslash' => ['ada\\payload@example.com'],
            'control character' => ["ada\x00@example.com"],
        ];
    }
}
