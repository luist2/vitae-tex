<?php

namespace App\Actions\Cvs;

use App\Models\Cv;
use Illuminate\Support\Facades\DB;

class SaveCv
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Cv $cv, array $data): Cv
    {
        return DB::transaction(function () use ($cv, $data): Cv {
            $cv->update([
                'title' => $data['title'],
                'template_key' => $data['template_key'],
                'full_name' => $data['full_name'],
                'professional_headline' => $this->optionalString($data['professional_headline'] ?? null),
                'contact_email' => $this->optionalString($data['contact_email'] ?? null),
                'phone' => $this->optionalString($data['phone'] ?? null),
                'location' => $this->optionalString($data['location'] ?? null),
                'professional_summary' => $this->optionalString($data['professional_summary'] ?? null),
            ]);

            $this->replaceWorkExperiences($cv, $data['work_experiences']);
            $this->replaceEducation($cv, $data['education_entries']);
            $this->replaceSkillGroups($cv, $data['skill_groups']);
            $this->replaceProjects($cv, $data['projects']);
            $this->replaceCertifications($cv, $data['certifications']);
            $this->replaceLinks($cv, $data['links']);
            $cv->increment('revision');

            return $cv->refresh()->load([
                'workExperiences',
                'educationEntries',
                'skillGroups.skills',
                'projects',
                'certifications',
                'links',
            ]);
        });
    }

    /** @param array<int, array<string, mixed>> $experiences */
    private function replaceWorkExperiences(Cv $cv, array $experiences): void
    {
        $cv->workExperiences()->delete();

        foreach (array_values($experiences) as $position => $experience) {
            $cv->workExperiences()->create([
                'employer' => $experience['employer'],
                'role' => $experience['role'],
                'location' => $this->optionalString($experience['location'] ?? null),
                'start_date' => $this->monthDate($experience['start_date']),
                'end_date' => $this->monthDate($experience['end_date'] ?? null),
                'is_current' => $experience['is_current'],
                'highlights' => array_values($experience['highlights']),
                'position' => $position,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function replaceEducation(Cv $cv, array $entries): void
    {
        $cv->educationEntries()->delete();

        foreach (array_values($entries) as $position => $entry) {
            $cv->educationEntries()->create([
                'institution' => $entry['institution'],
                'qualification' => $entry['qualification'],
                'field_of_study' => $this->optionalString($entry['field_of_study'] ?? null),
                'location' => $this->optionalString($entry['location'] ?? null),
                'start_date' => $this->monthDate($entry['start_date']),
                'end_date' => $this->monthDate($entry['end_date'] ?? null),
                'is_current' => $entry['is_current'],
                'description' => $this->optionalString($entry['description'] ?? null),
                'position' => $position,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $groups */
    private function replaceSkillGroups(Cv $cv, array $groups): void
    {
        $cv->skillGroups()->delete();

        foreach (array_values($groups) as $groupPosition => $groupData) {
            $group = $cv->skillGroups()->create([
                'name' => $groupData['name'],
                'position' => $groupPosition,
            ]);

            foreach (array_values($groupData['skills']) as $skillPosition => $skill) {
                $group->skills()->create([
                    'name' => $skill['name'],
                    'position' => $skillPosition,
                ]);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $projects */
    private function replaceProjects(Cv $cv, array $projects): void
    {
        $cv->projects()->delete();

        foreach (array_values($projects) as $position => $project) {
            $cv->projects()->create([
                'name' => $project['name'],
                'role' => $this->optionalString($project['role'] ?? null),
                'description' => $this->optionalString($project['description'] ?? null),
                'url' => $this->optionalString($project['url'] ?? null),
                'start_date' => $this->monthDate($project['start_date'] ?? null),
                'end_date' => $this->monthDate($project['end_date'] ?? null),
                'is_current' => $project['is_current'],
                'highlights' => array_values($project['highlights']),
                'technologies' => array_values($project['technologies']),
                'position' => $position,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $certifications */
    private function replaceCertifications(Cv $cv, array $certifications): void
    {
        $cv->certifications()->delete();

        foreach (array_values($certifications) as $position => $certification) {
            $cv->certifications()->create([
                'name' => $certification['name'],
                'issuer' => $certification['issuer'],
                'issued_on' => $this->monthDate($certification['issued_on'] ?? null),
                'expires_on' => $this->monthDate($certification['expires_on'] ?? null),
                'credential_id' => $this->optionalString($certification['credential_id'] ?? null),
                'credential_url' => $this->optionalString($certification['credential_url'] ?? null),
                'position' => $position,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $links */
    private function replaceLinks(Cv $cv, array $links): void
    {
        $cv->links()->delete();

        foreach (array_values($links) as $position => $link) {
            $cv->links()->create([
                'type' => $link['type'],
                'label' => $this->optionalString($link['label'] ?? null),
                'url' => $link['url'],
                'position' => $position,
            ]);
        }
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function monthDate(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value.'-01' : null;
    }
}
