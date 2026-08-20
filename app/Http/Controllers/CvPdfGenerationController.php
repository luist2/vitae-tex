<?php

namespace App\Http\Controllers;

use App\Models\Cv;
use App\Support\Documents\PdfCompilationException;
use App\Support\Documents\TectonicCompiler;
use App\Support\Latex\CvLatexRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CvPdfGenerationController extends Controller
{
    public function __invoke(
        Cv $cv,
        CvLatexRenderer $renderer,
        TectonicCompiler $compiler,
    ): Response|JsonResponse {
        Gate::authorize('generate', $cv);

        try {
            $pdf = $compiler->compile($renderer->render($cv));
        } catch (PdfCompilationException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_SERVICE_UNAVAILABLE,
                $this->privateHeaders(),
            );
        }

        return response($pdf, Response::HTTP_OK, [
            ...$this->privateHeaders(),
            'Content-Disposition' => 'inline; filename="'.$this->filename($cv).'"',
            'Content-Type' => 'application/pdf',
            'X-CV-Revision' => (string) $cv->revision,
        ]);
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function filename(Cv $cv): string
    {
        $slug = substr(Str::slug($cv->title), 0, 80);

        return ($slug !== '' ? $slug : 'cv-'.$cv->getKey()).'.pdf';
    }
}
