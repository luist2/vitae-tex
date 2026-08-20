<?php

namespace App\Http\Controllers;

use App\Models\Cv;
use App\Support\Latex\CvLatexRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvTexDownloadController extends Controller
{
    public function __invoke(Cv $cv, CvLatexRenderer $renderer): StreamedResponse
    {
        Gate::authorize('download', $cv);

        $source = $renderer->render($cv);

        return response()->streamDownload(
            static function () use ($source): void {
                echo $source;
            },
            $this->filename($cv),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Type' => 'application/x-tex; charset=UTF-8',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function filename(Cv $cv): string
    {
        $slug = substr(Str::slug($cv->title), 0, 80);

        return ($slug !== '' ? $slug : 'cv-'.$cv->getKey()).'.tex';
    }
}
