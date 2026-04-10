<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\AiServiceInterface::class, function ($app) {
            $provider = env('AI_PROVIDER', 'gemini');
            if ($provider === 'gemini') {
                return $app->make(\App\Services\GeminiService::class);
            }
            return $app->make(\App\Services\OllamaService::class);
        });

        $this->app->singleton(\App\Services\IntentClassificationService::class, function ($app) {
            return new \App\Services\IntentClassificationService($app->make(\App\Services\AiServiceInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
    }
}
