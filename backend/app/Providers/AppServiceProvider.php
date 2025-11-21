<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Auth::class, function ($app) {
            // Assuming the service account JSON path is in env or default location
            // If FIREBASE_CREDENTIALS is set in .env, the factory picks it up automatically
            // providing the file exists.

            // For this environment, if file is missing, we might want to handle it gracefully
            // or just let it fail (which is correct behavior).
            // However, since we don't have the file, we rely on our Mock fallback in AuthController for now
            // or we instantiate it carefully.

            $factory = (new Factory);

            // Only attempt to load credentials if env var is set and file exists to avoid startup errors
            if (env('FIREBASE_CREDENTIALS') && file_exists(env('FIREBASE_CREDENTIALS'))) {
                $factory = $factory->withServiceAccount(env('FIREBASE_CREDENTIALS'));
            }

            return $factory->createAuth();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
