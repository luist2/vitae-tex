<?php

use App\Http\Controllers\CvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return $request->user()
        ? to_route('cvs.index')
        : to_route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('cvs', [CvController::class, 'index'])->name('cvs.index');
    Route::post('cvs', [CvController::class, 'store'])->name('cvs.store');
    Route::get('cvs/{cv}/edit', [CvController::class, 'edit'])->name('cvs.edit');
    Route::post('cvs/{cv}/duplicate', [CvController::class, 'duplicate'])->name('cvs.duplicate');
    Route::delete('cvs/{cv}', [CvController::class, 'destroy'])->name('cvs.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
