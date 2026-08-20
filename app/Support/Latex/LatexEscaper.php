<?php

namespace App\Support\Latex;

use InvalidArgumentException;

final class LatexEscaper
{
    /** @var array<string, string> */
    private const TEXT_REPLACEMENTS = [
        '\\' => '\\textbackslash{}',
        '{' => '\\{',
        '}' => '\\}',
        '#' => '\\#',
        '$' => '\\$',
        '%' => '\\%',
        '&' => '\\&',
        '_' => '\\_',
        '^' => '\\textasciicircum{}',
        '~' => '\\textasciitilde{}',
    ];

    /** @var array<string, string> */
    private const DESTINATION_REPLACEMENTS = [
        '#' => '\\#',
        '$' => '\\$',
        '%' => '\\%',
        '&' => '\\&',
        '_' => '\\_',
        '^' => '\\textasciicircum{}',
        '~' => '\\textasciitilde{}',
    ];

    public function text(string $value): string
    {
        $this->assertValidText($value);

        return $this->escapeText(str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $value));
    }

    public function multiline(string $value): string
    {
        $this->assertValidText($value);

        $value = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $value);

        return implode("\n", array_map($this->escapeText(...), explode("\n", $value)));
    }

    public function listItem(string $value): string
    {
        return $this->text($value);
    }

    public function url(string $value): string
    {
        $this->assertSafeDestination($value);

        $parts = parse_url($value);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || $host === ''
            || filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            throw new InvalidArgumentException('La URL debe ser un destino HTTP o HTTPS válido.');
        }

        return $this->escapeDestination($value);
    }

    public function email(string $value): string
    {
        $this->assertSafeDestination($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El email debe ser una dirección válida.');
        }

        return $this->escapeDestination($value);
    }

    private function escapeText(string $value): string
    {
        return strtr($value, self::TEXT_REPLACEMENTS);
    }

    private function escapeDestination(string $value): string
    {
        return strtr($value, self::DESTINATION_REPLACEMENTS);
    }

    private function assertValidText(string $value): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('El texto contiene una codificación inválida.');
        }

        if (preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $value) === 1) {
            throw new InvalidArgumentException('El texto contiene caracteres de control no admitidos.');
        }
    }

    private function assertSafeDestination(string $value): void
    {
        $this->assertValidText($value);

        if ($value !== trim($value) || preg_match('/[\\\\{}\r\n\t]/u', $value) === 1) {
            throw new InvalidArgumentException('El destino contiene caracteres no admitidos.');
        }
    }
}
