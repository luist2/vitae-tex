<?php

namespace Tests\Feature\Cvs;

use App\Http\Requests\Cvs\UpdateCvRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvValidationTranslationTest extends TestCase
{
    /**
     * @param  array<int, string>  $rules
     */
    #[DataProvider('nestedAttributeProvider')]
    public function test_editor_nested_attributes_have_friendly_names(
        string $wildcardField,
        mixed $invalidValue,
        array $rules,
        string $expectedMessage,
    ): void {
        $field = str_replace('*', '0', $wildcardField);
        $data = [];
        data_set($data, $field, $invalidValue);
        $validator = Validator::make($data, [$wildcardField => $rules]);

        $this->assertTrue($validator->fails());
        $this->assertSame($expectedMessage, $validator->errors()->first($field));
    }

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

    public function test_custom_link_requirement_uses_friendly_names_for_both_fields(): void
    {
        $validator = Validator::make(
            ['links' => [['type' => 'other', 'label' => '']]],
            ['links.*.label' => ['required_if:links.*.type,other']],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Debes completar etiqueta del enlace cuando tipo de enlace es other.',
            $validator->errors()->first('links.0.label'),
        );
    }

    public function test_every_editor_rule_has_a_friendly_attribute_name(): void
    {
        $attributes = Lang::get('validation.attributes');

        $this->assertIsArray($attributes);

        foreach (array_keys((new UpdateCvRequest)->rules()) as $field) {
            $this->assertArrayHasKey($field, $attributes, "The editor field [{$field}] needs a friendly validation name.");
        }
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
    public static function experienceAndEducationAttributeProvider(): array
    {
        return [
            'experience employer' => ['work_experiences.*.employer', [], ['string'], 'Nombre de la empresa debe ser texto.'],
            'experience role' => ['work_experiences.*.role', [], ['string'], 'Cargo debe ser texto.'],
            'experience location' => ['work_experiences.*.location', [], ['string'], 'Ubicación debe ser texto.'],
            'experience start month' => ['work_experiences.*.start_date', '2026-1', ['date_format:Y-m'], 'Mes de inicio debe tener el formato Y-m.'],
            'experience end month' => ['work_experiences.*.end_date', '2026-1', ['date_format:Y-m'], 'Mes de término debe tener el formato Y-m.'],
            'experience current state' => ['work_experiences.*.is_current', 'quizás', ['boolean'], 'Estado actual de la experiencia debe ser verdadero o falso.'],
            'experience highlights' => ['work_experiences.*.highlights', 'texto', ['array'], 'Lista de puntos destacados debe ser una lista.'],
            'experience highlight' => ['work_experiences.*.highlights.*', [], ['string'], 'Punto destacado debe ser texto.'],
            'education institution' => ['education_entries.*.institution', [], ['string'], 'Nombre de la institución debe ser texto.'],
            'education qualification' => ['education_entries.*.qualification', [], ['string'], 'Título o grado debe ser texto.'],
            'education field of study' => ['education_entries.*.field_of_study', [], ['string'], 'Área de estudio debe ser texto.'],
            'education location' => ['education_entries.*.location', [], ['string'], 'Ubicación debe ser texto.'],
            'education start month' => ['education_entries.*.start_date', '2026-1', ['date_format:Y-m'], 'Mes de inicio debe tener el formato Y-m.'],
            'education end month' => ['education_entries.*.end_date', '2026-1', ['date_format:Y-m'], 'Mes de término debe tener el formato Y-m.'],
            'education current state' => ['education_entries.*.is_current', 'quizás', ['boolean'], 'Estado actual de los estudios debe ser verdadero o falso.'],
            'education description' => ['education_entries.*.description', [], ['string'], 'Descripción debe ser texto.'],
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

    /**
     * @return array<string, array{string, mixed, array<int, string>, string}>
     */
    public static function nestedAttributeProvider(): array
    {
        return [
            ...self::experienceAndEducationAttributeProvider(),
            ...self::skillAttributeProvider(),
            ...self::projectCertificationAndLinkAttributeProvider(),
        ];
    }

    /**
     * @return array<string, array{string, mixed, array<int, string>, string}>
     */
    public static function projectCertificationAndLinkAttributeProvider(): array
    {
        return [
            'project name' => ['projects.*.name', [], ['string'], 'Nombre del proyecto debe ser texto.'],
            'project role' => ['projects.*.role', [], ['string'], 'Rol debe ser texto.'],
            'project description' => ['projects.*.description', [], ['string'], 'Descripción debe ser texto.'],
            'project URL' => ['projects.*.url', 'ftp://example.com', ['url:http,https'], 'URL del proyecto debe ser una URL válida.'],
            'project start month' => ['projects.*.start_date', '2026-1', ['date_format:Y-m'], 'Mes de inicio debe tener el formato Y-m.'],
            'project end month' => ['projects.*.end_date', '2026-1', ['date_format:Y-m'], 'Mes de término debe tener el formato Y-m.'],
            'project current state' => ['projects.*.is_current', 'quizás', ['boolean'], 'Estado actual del proyecto debe ser verdadero o falso.'],
            'project highlights' => ['projects.*.highlights', 'texto', ['array'], 'Lista de puntos destacados debe ser una lista.'],
            'project highlight' => ['projects.*.highlights.*', [], ['string'], 'Punto destacado debe ser texto.'],
            'project technologies' => ['projects.*.technologies', 'texto', ['array'], 'Lista de tecnologías debe ser una lista.'],
            'project technology' => ['projects.*.technologies.*', [], ['string'], 'Tecnología debe ser texto.'],
            'certification name' => ['certifications.*.name', [], ['string'], 'Nombre de la certificación debe ser texto.'],
            'certification issuer' => ['certifications.*.issuer', [], ['string'], 'Emisor debe ser texto.'],
            'certification issue month' => ['certifications.*.issued_on', '2026-1', ['date_format:Y-m'], 'Mes de emisión debe tener el formato Y-m.'],
            'certification expiration month' => ['certifications.*.expires_on', '2026-1', ['date_format:Y-m'], 'Mes de expiración debe tener el formato Y-m.'],
            'certification credential ID' => ['certifications.*.credential_id', [], ['string'], 'ID de credencial debe ser texto.'],
            'certification credential URL' => ['certifications.*.credential_url', 'ftp://example.com', ['url:http,https'], 'URL de credencial debe ser una URL válida.'],
            'link type' => ['links.*.type', 'desconocido', ['in:linkedin,github,portfolio,other'], 'El valor seleccionado para tipo de enlace no es válido.'],
            'link label' => ['links.*.label', [], ['string'], 'Etiqueta del enlace debe ser texto.'],
            'link URL' => ['links.*.url', 'ftp://example.com', ['url:http,https'], 'URL del enlace debe ser una URL válida.'],
        ];
    }

    /**
     * @return array<string, array{string, mixed, array<int, string>, string}>
     */
    public static function skillAttributeProvider(): array
    {
        return [
            'group name' => ['skill_groups.*.name', [], ['string'], 'Nombre del grupo debe ser texto.'],
            'group skills' => ['skill_groups.*.skills', 'texto', ['array'], 'Lista de habilidades del grupo debe ser una lista.'],
            'skill name' => ['skill_groups.*.skills.*.name', [], ['string'], 'Nombre de la habilidad debe ser texto.'],
        ];
    }
}
