<?php

namespace App\Http\Controllers;

use App\Actions\Cvs\DuplicateCv;
use App\Actions\Cvs\SaveCv;
use App\Http\Requests\Cvs\StoreCvRequest;
use App\Http\Requests\Cvs\UpdateCvRequest;
use App\Http\Resources\CvEditorResource;
use App\Models\Cv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CvController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Cv::class);

        $cvs = $request->user()->cvs()
            ->latest('updated_at')
            ->latest('id')
            ->get(['id', 'title', 'template_key', 'updated_at']);

        return Inertia::render('Cvs/Index', [
            'cvs' => $cvs,
        ]);
    }

    public function store(StoreCvRequest $request): RedirectResponse
    {
        $cv = $request->user()->cvs()->create([
            'title' => $request->validated('title'),
            'template_key' => config('cv.default_template'),
        ]);

        return to_route('cvs.edit', $cv)
            ->with('success', 'CV creado correctamente.');
    }

    public function edit(Cv $cv): Response
    {
        Gate::authorize('view', $cv);

        $cv->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $template = config("cv.templates.{$cv->template_key}");

        return Inertia::render('Cvs/Edit', [
            'cv' => CvEditorResource::make($cv)->resolve(),
            'template' => [
                'key' => $cv->template_key,
                'name' => $template['name'],
                'sections' => $template['sections'],
            ],
        ]);
    }

    public function update(UpdateCvRequest $request, Cv $cv, SaveCv $saveCv): RedirectResponse
    {
        $saveCv->handle($cv, $request->validated());

        return to_route('cvs.edit', $cv)
            ->with('success', 'CV guardado correctamente.');
    }

    public function duplicate(Cv $cv, DuplicateCv $duplicateCv): RedirectResponse
    {
        Gate::authorize('duplicate', $cv);

        $copy = $duplicateCv->handle($cv);

        return to_route('cvs.edit', $copy)
            ->with('success', 'CV duplicado correctamente.');
    }

    public function destroy(Cv $cv): RedirectResponse
    {
        Gate::authorize('delete', $cv);

        $cv->delete();

        return to_route('cvs.index')
            ->with('success', 'CV eliminado permanentemente.');
    }
}
