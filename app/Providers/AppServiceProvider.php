<?php

namespace App\Providers;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('cv', function (string $value): Cv {
            $user = request()->user();
            $id = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if (! $user instanceof User || $id === false) {
                throw (new ModelNotFoundException)->setModel(Cv::class, [$value]);
            }

            return $user->cvs()->findOrFail($id);
        });
    }
}
