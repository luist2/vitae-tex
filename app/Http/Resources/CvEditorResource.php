<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CvEditorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'template_key' => $this->template_key,
            'full_name' => $this->full_name ?? '',
            'professional_headline' => $this->professional_headline,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'location' => $this->location,
            'professional_summary' => $this->professional_summary,
            'work_experiences' => $this->workExperiences->map(fn ($experience): array => [
                'employer' => $experience->employer,
                'role' => $experience->role,
                'location' => $experience->location,
                'start_date' => $experience->start_date->format('Y-m'),
                'end_date' => $experience->end_date?->format('Y-m'),
                'is_current' => $experience->is_current,
                'highlights' => $experience->highlights,
            ])->all(),
            'education_entries' => $this->educationEntries->map(fn ($education): array => [
                'institution' => $education->institution,
                'qualification' => $education->qualification,
                'field_of_study' => $education->field_of_study,
                'location' => $education->location,
                'start_date' => $education->start_date->format('Y-m'),
                'end_date' => $education->end_date?->format('Y-m'),
                'is_current' => $education->is_current,
                'description' => $education->description,
            ])->all(),
            'skill_groups' => $this->skillGroups->map(fn ($group): array => [
                'name' => $group->name,
                'skills' => $group->skills->map(fn ($skill): array => [
                    'name' => $skill->name,
                ])->all(),
            ])->all(),
            'projects' => $this->projects->map(fn ($project): array => [
                'name' => $project->name,
                'role' => $project->role,
                'description' => $project->description,
                'url' => $project->url,
                'start_date' => $project->start_date?->format('Y-m'),
                'end_date' => $project->end_date?->format('Y-m'),
                'is_current' => $project->is_current,
                'highlights' => $project->highlights,
                'technologies' => $project->technologies,
            ])->all(),
            'certifications' => $this->certifications->map(fn ($certification): array => [
                'name' => $certification->name,
                'issuer' => $certification->issuer,
                'issued_on' => $certification->issued_on?->format('Y-m'),
                'expires_on' => $certification->expires_on?->format('Y-m'),
                'credential_id' => $certification->credential_id,
                'credential_url' => $certification->credential_url,
            ])->all(),
            'links' => $this->links->map(fn ($link): array => [
                'type' => $link->type,
                'label' => $link->label,
                'url' => $link->url,
            ])->all(),
            'revision' => $this->revision,
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
