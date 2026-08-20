<?php

namespace App\Support\Latex;

use App\Models\Cv;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;

final class CvLatexRenderer
{
    /** @var array<string, string> */
    private const SECTION_DATA_KEYS = [
        'professional_summary' => 'professional_summary',
        'education' => 'education_entries',
        'work_experience' => 'work_experiences',
        'projects' => 'projects',
        'skills' => 'skill_groups',
        'certifications' => 'certifications',
    ];

    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    public function __construct(
        private readonly LatexEscaper $escaper,
        private readonly ViewFactory $views,
    ) {}

    public function render(Cv $cv): string
    {
        if (! $cv->exists) {
            throw new InvalidArgumentException('El CV debe existir antes de renderizarse.');
        }

        $persistedCv = Cv::query()->findOrFail($cv->getKey());
        $persistedCv->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $template = config("cv.templates.{$persistedCv->template_key}");

        if (! $this->isValidTemplate($template)) {
            throw new InvalidArgumentException('La plantilla del CV no está disponible.');
        }

        $document = $this->document($persistedCv);
        $document['sections'] = $this->visibleSections($template['sections'], $document);

        return $this->views->make($template['view'], ['document' => $document])->render();
    }

    private function isValidTemplate(mixed $template): bool
    {
        if (! is_array($template) || ! is_string($template['view'] ?? null) || ! is_array($template['sections'] ?? null)) {
            return false;
        }

        if (! $this->views->exists($template['view'])) {
            return false;
        }

        foreach ($template['sections'] as $section) {
            if (! is_string($section) || ! array_key_exists($section, self::SECTION_DATA_KEYS)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function document(Cv $cv): array
    {
        return [
            'full_name' => $this->optionalText($cv->full_name) ?? '',
            'header_details' => $this->headerDetails($cv),
            'contacts' => $this->contacts($cv),
            'professional_summary' => $this->optionalMultiline($cv->professional_summary),
            'education_entries' => $cv->educationEntries->map(fn ($entry): array => [
                'institution' => $this->escaper->text($entry->institution),
                'qualification' => $this->educationQualification(
                    $entry->qualification,
                    $entry->field_of_study,
                ),
                'location' => $this->optionalText($entry->location),
                'dates' => $this->dateRange($entry->start_date, $entry->end_date, $entry->is_current),
                'description' => $this->optionalText($entry->description),
            ])->all(),
            'work_experiences' => $cv->workExperiences->map(fn ($experience): array => [
                'employer' => $this->escaper->text($experience->employer),
                'role' => $this->escaper->text($experience->role),
                'location' => $this->optionalText($experience->location),
                'dates' => $this->dateRange($experience->start_date, $experience->end_date, $experience->is_current),
                'highlights' => array_map(
                    fn (mixed $highlight): string => $this->escaper->listItem((string) $highlight),
                    $experience->highlights ?? [],
                ),
            ])->all(),
            'projects' => $cv->projects->map(fn ($project): array => [
                'name' => $this->escaper->text($project->name),
                'role' => $this->optionalText($project->role),
                'description' => $this->optionalText($project->description),
                'destination' => $this->optionalUrl($project->url),
                'dates' => $this->dateRange($project->start_date, $project->end_date, $project->is_current),
                'highlights' => array_map(
                    fn (mixed $highlight): string => $this->escaper->listItem((string) $highlight),
                    $project->highlights ?? [],
                ),
                'technologies' => array_map(
                    fn (mixed $technology): string => $this->escaper->text((string) $technology),
                    $project->technologies ?? [],
                ),
            ])->all(),
            'skill_groups' => $cv->skillGroups->map(fn ($group): array => [
                'name' => $this->escaper->text($group->name),
                'skills' => $group->skills->map(
                    fn ($skill): string => $this->escaper->text($skill->name),
                )->all(),
            ])->all(),
            'certifications' => $cv->certifications->map(fn ($certification): array => [
                'name' => $this->escaper->text($certification->name),
                'issuer' => $this->escaper->text($certification->issuer),
                'dates' => $this->certificationDates($certification->issued_on, $certification->expires_on),
                'credential' => $this->credential(
                    $certification->credential_id,
                    $certification->credential_url,
                ),
            ])->all(),
        ];
    }

    /** @return list<string> */
    private function headerDetails(Cv $cv): array
    {
        return array_values(array_filter([
            $this->optionalText($cv->professional_headline),
            $this->optionalText($cv->location),
        ], fn (?string $value): bool => $value !== null));
    }

    /** @return list<array{label: string, destination: ?string}> */
    private function contacts(Cv $cv): array
    {
        $contacts = [];

        if (is_string($cv->contact_email) && $cv->contact_email !== '') {
            $contacts[] = [
                'label' => $this->escaper->text($cv->contact_email),
                'destination' => 'mailto:'.$this->escaper->email($cv->contact_email),
            ];
        }

        if (is_string($cv->phone) && $cv->phone !== '') {
            $contacts[] = [
                'label' => $this->escaper->text($cv->phone),
                'destination' => null,
            ];
        }

        foreach ($cv->links as $link) {
            $label = $this->optionalText($link->label) ?? match ($link->type) {
                'linkedin' => 'LinkedIn',
                'github' => 'GitHub',
                'portfolio' => 'Portafolio',
                default => 'Enlace',
            };

            $contacts[] = [
                'label' => $label,
                'destination' => $this->escaper->url($link->url),
            ];
        }

        return $contacts;
    }

    /** @return array{label: string, destination: ?string}|null */
    private function credential(?string $id, ?string $url): ?array
    {
        $label = $this->optionalText($id);
        $destination = $this->optionalUrl($url);

        if ($label === null && $destination === null) {
            return null;
        }

        return [
            'label' => $label ?? 'Ver credencial',
            'destination' => $destination,
        ];
    }

    private function optionalText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $this->escaper->text($value) : null;
    }

    private function optionalMultiline(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $this->escaper->multiline($value) : null;
    }

    private function optionalUrl(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $this->escaper->url($value) : null;
    }

    private function educationQualification(string $qualification, ?string $fieldOfStudy): string
    {
        $value = $this->escaper->text($qualification);
        $field = $this->optionalText($fieldOfStudy);

        return $field === null ? $value : $value.' en '.$field;
    }

    private function dateRange(?CarbonInterface $start, ?CarbonInterface $end, bool $isCurrent): ?string
    {
        if ($start === null) {
            return null;
        }

        $range = $this->month($start);

        if ($isCurrent) {
            return $range.' -- Actualidad';
        }

        return $end === null ? $range : $range.' -- '.$this->month($end);
    }

    private function certificationDates(?CarbonInterface $issuedOn, ?CarbonInterface $expiresOn): ?string
    {
        if ($issuedOn === null) {
            return null;
        }

        $dates = $this->month($issuedOn);

        return $expiresOn === null ? $dates : $dates.' -- '.$this->month($expiresOn);
    }

    private function month(CarbonInterface $date): string
    {
        return self::MONTHS[$date->month].' '.$date->year;
    }

    /**
     * @param  list<mixed>  $configuredSections
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function visibleSections(array $configuredSections, array $document): array
    {
        return array_values(array_filter(
            $configuredSections,
            function (mixed $section) use ($document): bool {
                $content = $document[self::SECTION_DATA_KEYS[$section]];

                return is_array($content) ? $content !== [] : $content !== null && $content !== '';
            },
        ));
    }
}
