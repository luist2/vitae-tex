<?php

use App\Http\Controllers\CvController;
use App\Http\Controllers\CvPdfGenerationController;
use App\Http\Controllers\CvTexDownloadController;
use App\Http\Middleware\EnforceCvEditorPayloadLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    return $request->user()
        ? to_route('cvs.index')
        : to_route('login');
})->name('home');

Route::get('privacidad', function () {
    return Inertia::render('Legal/Privacy', [
        'contactEmail' => config('privacy.contact_email'),
    ]);
})->name('privacy');

Route::middleware('auth')->group(function () {
    Route::get('cvs', [CvController::class, 'index'])->name('cvs.index');
    Route::post('cvs', [CvController::class, 'store'])->name('cvs.store');
    Route::get('cvs/{cv}/edit', [CvController::class, 'edit'])->name('cvs.edit');
    Route::get('cvs/{cv}/download/tex', CvTexDownloadController::class)->name('cvs.download.tex');
    Route::post('cvs/{cv}/generate/pdf', CvPdfGenerationController::class)
        ->middleware('throttle:cv-pdf-generation')
        ->name('cvs.generate.pdf');
    Route::patch('cvs/{cv}', [CvController::class, 'update'])
        ->middleware(EnforceCvEditorPayloadLimit::class)
        ->name('cvs.update');
    Route::post('cvs/{cv}/duplicate', [CvController::class, 'duplicate'])->name('cvs.duplicate');
    Route::delete('cvs/{cv}', [CvController::class, 'destroy'])->name('cvs.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
