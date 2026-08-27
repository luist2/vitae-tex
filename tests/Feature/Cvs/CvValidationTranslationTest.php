<?php

namespace Tests\Feature\Cvs;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvValidationTranslationTest extends TestCase
{
    /**
     * @param  array<int, string>  $rules
     */
    #[DataProvider('rootAttributeProvider')]
    public function test_editor_root_attributes_have_friendly_names(
        string $field,
        mixed $invalidValue,
        array $rules,
        string $expectedMessage,
    ): void {
        $validator = Validator::make([$field => $invalidValue], [$field => $rules]);

        $this->assertTrue($validator->fails());
        $this->assertSame($expectedMessage, $validator->errors()->first($field));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, string>>  $rules
     */
    #[DataProvider('editorRuleProvider')]
    public function test_editor_rules_have_spanish_validation_messages(
        array $data,
        array $rules,
        string $field,
        string $expectedMessage,
    ): void {
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertSame($expectedMessage, $validator->errors()->first($field));
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, array<int, string>>, string, string}>
     */
    public static function editorRuleProvider(): array
    {
        return [
            'array' => [
                ['contenido' => 'texto'],
                ['contenido' => ['array']],
                'contenido',
                'Contenido debe ser una lista.',
            ],
            'boolean' => [
                ['actual' => 'quizás'],
                ['actual' => ['boolean']],
                'actual',
                'Actual debe ser verdadero o falso.',
            ],
            'date format' => [
                ['fecha' => '2026-1'],
                ['fecha' => ['date_format:Y-m']],
                'fecha',
                'Fecha debe tener el formato Y-m.',
            ],
            'allowed value' => [
                ['tipo' => 'desconocido'],
                ['tipo' => ['in:permitido']],
                'tipo',
                'El valor seleccionado para tipo no es válido.',
            ],
            'list' => [
                ['elementos' => [1 => 'primero']],
                ['elementos' => ['list']],
                'elementos',
                'Elementos debe ser una lista válida.',
            ],
            'present' => [
                [],
                ['sección' => ['present']],
                'sección',
                'Sección debe estar presente.',
            ],
            'conditionally required' => [
                ['tipo' => 'otro'],
                ['etiqueta' => ['required_if:tipo,otro']],
                'etiqueta',
                'Debes completar etiqueta cuando tipo es otro.',
            ],
            'url' => [
                ['enlace' => 'ftp://example.com'],
                ['enlace' => ['url:http,https']],
                'enlace',
                'Enlace debe ser una URL válida.',
            ],
        ];
    }

    /**
     * @return array<string, array{string, mixed, array<int, string>, string}>
     */
    public static function rootAttributeProvider(): array
    {
        return [
            'internal title' => ['title', [], ['string'], 'Título interno debe ser texto.'],
            'template' => ['template_key', [], ['string'], 'Plantilla debe ser texto.'],
            'full name' => ['full_name', [], ['string'], 'Nombre completo debe ser texto.'],
            'professional headline' => ['professional_headline', [], ['string'], 'Titular profesional debe ser texto.'],
            'contact email' => ['contact_email', [], ['string'], 'Email de contacto debe ser texto.'],
            'phone' => ['phone', [], ['string'], 'Teléfono debe ser texto.'],
            'location' => ['location', [], ['string'], 'Ubicación debe ser texto.'],
            'professional summary' => ['professional_summary', [], ['string'], 'Resumen profesional debe ser texto.'],
            'work experiences' => ['work_experiences', 'texto', ['array'], 'Sección de experiencia laboral debe ser una lista.'],
            'education' => ['education_entries', 'texto', ['array'], 'Sección de educación debe ser una lista.'],
            'skill groups' => ['skill_groups', 'texto', ['array'], 'Sección de habilidades técnicas debe ser una lista.'],
            'projects' => ['projects', 'texto', ['array'], 'Sección de proyectos debe ser una lista.'],
            'certifications' => ['certifications', 'texto', ['array'], 'Sección de certificaciones debe ser una lista.'],
            'links' => ['links', 'texto', ['array'], 'Sección de enlaces de contacto debe ser una lista.'],
        ];
    }
}
