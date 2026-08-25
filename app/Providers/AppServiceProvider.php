<?php

namespace App\Providers;

use App\Models\Cv;
use App\Models\User;
use App\Support\Database\PostgresTlsGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        Mail::extend('brevo', function () {
            $apiKey = config('services.brevo.key');

            if (! is_string($apiKey) || trim($apiKey) === '') {
                throw new LogicException('La API key de Brevo no está configurada.');
            }

            $httpClient = HttpClient::create([
                'timeout' => max(1, (int) config('services.brevo.timeout_seconds')),
            ]);

            return (new BrevoTransportFactory(client: $httpClient))->create(
                new Dsn('brevo+api', 'default', $apiKey),
            );
        });

        PostgresTlsGuard::assertSecure(
            $this->app->environment(),
            (string) config('database.default'),
            config('database.connections.pgsql.sslmode'),
        );

        RateLimiter::for('cv-pdf-generation', function (Request $request): Limit {
            $user = $request->user();

            if (! $user instanceof User) {
                return Limit::none();
            }

            return Limit::perMinute(max(1, (int) config('cv.pdf.rate_limit_per_minute')))
                ->by('cv-pdf-generation:'.$user->getAuthIdentifier());
        });

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
