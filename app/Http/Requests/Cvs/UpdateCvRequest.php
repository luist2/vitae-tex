<?php

namespace App\Http\Requests\Cvs;

use App\Models\Cv;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCvRequest extends FormRequest
{
    /** @var array<int, string> */
    private const ROOT_FIELDS = [
        'title',
        'template_key',
        'full_name',
        'professional_headline',
        'contact_email',
        'phone',
        'location',
        'professional_summary',
        'work_experiences',
        'education_entries',
        'skill_groups',
        'projects',
        'certifications',
        'links',
    ];

    /** @var array<string, array<int, string>> */
    private const COLLECTION_FIELDS = [
        'work_experiences' => ['employer', 'role', 'location', 'start_date', 'end_date', 'is_current', 'highlights'],
        'education_entries' => ['institution', 'qualification', 'field_of_study', 'location', 'start_date', 'end_date', 'is_current', 'description'],
        'skill_groups' => ['name', 'skills'],
        'projects' => ['name', 'role', 'description', 'url', 'start_date', 'end_date', 'is_current', 'highlights', 'technologies'],
        'certifications' => ['name', 'issuer', 'issued_on', 'expires_on', 'credential_id', 'credential_url'],
        'links' => ['type', 'label', 'url'],
    ];

    public function authorize(): bool
    {
        $cv = $this->route('cv');

        return $cv instanceof Cv && ($this->user()?->can('update', $cv) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'template_key' => ['required', 'string', Rule::in(array_keys(config('cv.templates', [])))],
            'full_name' => ['required', 'string', 'max:120'],
            'professional_headline' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:120'],
            'professional_summary' => ['nullable', 'string', 'max:1200'],

            'work_experiences' => ['present', 'array', 'list', 'max:15'],
            'work_experiences.*.employer' => ['required', 'string', 'max:120'],
            'work_experiences.*.role' => ['required', 'string', 'max:120'],
            'work_experiences.*.location' => ['nullable', 'string', 'max:120'],
            'work_experiences.*.start_date' => ['required', 'date_format:Y-m'],
            'work_experiences.*.end_date' => ['nullable', 'date_format:Y-m'],
            'work_experiences.*.is_current' => ['required', 'boolean'],
            'work_experiences.*.highlights' => ['present', 'array', 'list', 'max:8'],
            'work_experiences.*.highlights.*' => ['required', 'string', 'max:300'],

            'education_entries' => ['present', 'array', 'list', 'max:10'],
            'education_entries.*.institution' => ['required', 'string', 'max:120'],
            'education_entries.*.qualification' => ['required', 'string', 'max:160'],
            'education_entries.*.field_of_study' => ['nullable', 'string', 'max:120'],
            'education_entries.*.location' => ['nullable', 'string', 'max:120'],
            'education_entries.*.start_date' => ['required', 'date_format:Y-m'],
            'education_entries.*.end_date' => ['nullable', 'date_format:Y-m'],
            'education_entries.*.is_current' => ['required', 'boolean'],
            'education_entries.*.description' => ['nullable', 'string', 'max:600'],

            'skill_groups' => ['present', 'array', 'list', 'max:10'],
            'skill_groups.*.name' => ['required', 'string', 'max:60'],
            'skill_groups.*.skills' => ['present', 'array', 'list', 'min:1', 'max:20'],
            'skill_groups.*.skills.*.name' => ['required', 'string', 'max:80'],

            'projects' => ['present', 'array', 'list', 'max:15'],
            'projects.*.name' => ['required', 'string', 'max:120'],
            'projects.*.role' => ['nullable', 'string', 'max:120'],
            'projects.*.description' => ['nullable', 'string', 'max:600'],
            'projects.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'projects.*.start_date' => ['nullable', 'date_format:Y-m'],
            'projects.*.end_date' => ['nullable', 'date_format:Y-m'],
            'projects.*.is_current' => ['required', 'boolean'],
            'projects.*.highlights' => ['present', 'array', 'list', 'max:8'],
            'projects.*.highlights.*' => ['required', 'string', 'max:300'],
            'projects.*.technologies' => ['present', 'array', 'list', 'max:20'],
            'projects.*.technologies.*' => ['required', 'string', 'max:60'],

            'certifications' => ['present', 'array', 'list', 'max:20'],
            'certifications.*.name' => ['required', 'string', 'max:160'],
            'certifications.*.issuer' => ['required', 'string', 'max:120'],
            'certifications.*.issued_on' => ['nullable', 'date_format:Y-m'],
            'certifications.*.expires_on' => ['nullable', 'date_format:Y-m'],
            'certifications.*.credential_id' => ['nullable', 'string', 'max:120'],
            'certifications.*.credential_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],

            'links' => ['present', 'array', 'list', 'max:8'],
            'links.*.type' => ['required', 'string', Rule::in(['linkedin', 'github', 'portfolio', 'other'])],
            'links.*.label' => ['nullable', 'required_if:links.*.type,other', 'string', 'max:60'],
            'links.*.url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateContact($validator);
                $this->validateDatedCollections($validator);
                $this->validateSkillCount($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->only(self::ROOT_FIELDS);

        foreach (self::COLLECTION_FIELDS as $collection => $fields) {
            if (array_key_exists($collection, $input)) {
                $input[$collection] = $this->onlyCollectionFields($input[$collection], $fields);
            }
        }

        if (is_array($input['skill_groups'] ?? null)) {
            foreach ($input['skill_groups'] as &$group) {
                if (is_array($group) && array_key_exists('skills', $group)) {
                    $group['skills'] = $this->onlyCollectionFields($group['skills'], ['name']);
                }
            }
            unset($group);
        }

        $this->replace($this->trimStrings($input));
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function onlyCollectionFields(mixed $value, array $fields): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(
            static fn (mixed $item): mixed => is_array($item) ? Arr::only($item, $fields) : $item,
            $value,
        );
    }

    private function validateContact(Validator $validator): void
    {
        $hasEmail = is_string($this->input('contact_email')) && $this->input('contact_email') !== '';
        $hasPhone = is_string($this->input('phone')) && $this->input('phone') !== '';
        $links = $this->input('links');
        $hasLink = is_array($links) && $links !== [];

        if (! $hasEmail && ! $hasPhone && ! $hasLink) {
            $validator->errors()->add(
                'contact_email',
                'Debes indicar al menos un email, teléfono o enlace de contacto.',
            );
        }
    }

    private function validateDatedCollections(Validator $validator): void
    {
        foreach (['work_experiences', 'education_entries'] as $collection) {
            foreach ($this->arrayInput($collection) as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $isCurrent = filter_var($entry['is_current'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                $endDate = $entry['end_date'] ?? null;

                if ($isCurrent === true && $this->hasValue($endDate)) {
                    $validator->errors()->add("{$collection}.{$index}.end_date", 'Una entrada actual no puede tener fecha de término.');
                }

                if ($isCurrent === false && ! $this->hasValue($endDate)) {
                    $validator->errors()->add("{$collection}.{$index}.end_date", 'La fecha de término es obligatoria si la entrada no es actual.');
                }

                $this->validateRange($validator, $collection, $index, $entry['start_date'] ?? null, $endDate);
            }
        }

        foreach ($this->arrayInput('projects') as $index => $project) {
            if (! is_array($project)) {
                continue;
            }

            $startDate = $project['start_date'] ?? null;
            $endDate = $project['end_date'] ?? null;
            $isCurrent = filter_var($project['is_current'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if (($this->hasValue($endDate) || $isCurrent === true) && ! $this->hasValue($startDate)) {
                $validator->errors()->add('projects.'.$index.'.start_date', 'La fecha inicial es obligatoria para este proyecto.');
            }

            if ($isCurrent === true && $this->hasValue($endDate)) {
                $validator->errors()->add('projects.'.$index.'.end_date', 'Un proyecto actual no puede tener fecha de término.');
            }

            $this->validateRange($validator, 'projects', $index, $startDate, $endDate);
        }

        foreach ($this->arrayInput('certifications') as $index => $certification) {
            if (! is_array($certification)) {
                continue;
            }

            $issuedOn = $certification['issued_on'] ?? null;
            $expiresOn = $certification['expires_on'] ?? null;

            if ($this->hasValue($expiresOn) && ! $this->hasValue($issuedOn)) {
                $validator->errors()->add('certifications.'.$index.'.issued_on', 'La fecha de emisión es obligatoria si existe una expiración.');
            }

            $this->validateRange($validator, 'certifications', $index, $issuedOn, $expiresOn, 'expires_on');
        }
    }

    private function validateSkillCount(Validator $validator): void
    {
        $total = 0;

        foreach ($this->arrayInput('skill_groups') as $group) {
            if (is_array($group) && is_array($group['skills'] ?? null)) {
                $total += count($group['skills']);
            }
        }

        if ($total > 100) {
            $validator->errors()->add('skill_groups', 'El CV no puede contener más de 100 habilidades en total.');
        }
    }

    private function validateRange(
        Validator $validator,
        string $collection,
        int|string $index,
        mixed $start,
        mixed $end,
        string $endField = 'end_date',
    ): void {
        if (! $this->isMonth($start) || ! $this->isMonth($end)) {
            return;
        }

        if ($end < $start) {
            $validator->errors()->add(
                "{$collection}.{$index}.{$endField}",
                'La fecha final no puede ser anterior a la fecha inicial.',
            );
        }
    }

    /**
     * @return array<mixed>
     */
    private function arrayInput(string $key): array
    {
        $value = $this->input($key);

        return is_array($value) ? $value : [];
    }

    private function hasValue(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    private function isMonth(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
    }

    private function trimStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(fn (mixed $item): mixed => $this->trimStrings($item), $value);
    }
}
