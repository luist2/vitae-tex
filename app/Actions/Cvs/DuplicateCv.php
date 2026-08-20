<?php

namespace App\Actions\Cvs;

use App\Models\Cv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateCv
{
    public function handle(Cv $cv): Cv
    {
        return DB::transaction(function () use ($cv): Cv {
            $cv->loadMissing([
                'workExperiences',
                'educationEntries',
                'skillGroups.skills',
                'projects',
                'certifications',
                'links',
            ]);

            $copy = $cv->replicate(['revision']);
            $copy->title = $this->copyTitle($cv->title);
            $copy->revision = 1;
            $copy->save();

            foreach ($cv->workExperiences as $workExperience) {
                $copy->workExperiences()->save($workExperience->replicate());
            }

            foreach ($cv->educationEntries as $educationEntry) {
                $copy->educationEntries()->save($educationEntry->replicate());
            }

            foreach ($cv->skillGroups as $skillGroup) {
                $skillGroupCopy = $copy->skillGroups()->save($skillGroup->replicate());

                foreach ($skillGroup->skills as $skill) {
                    $skillGroupCopy->skills()->save($skill->replicate());
                }
            }

            foreach ($cv->projects as $project) {
                $copy->projects()->save($project->replicate());
            }

            foreach ($cv->certifications as $certification) {
                $copy->certifications()->save($certification->replicate());
            }

            foreach ($cv->links as $link) {
                $copy->links()->save($link->replicate());
            }

            return $copy;
        });
    }

    private function copyTitle(string $title): string
    {
        $suffix = ' (copia)';

        return Str::limit($title, 100 - mb_strlen($suffix), '').$suffix;
    }
}
