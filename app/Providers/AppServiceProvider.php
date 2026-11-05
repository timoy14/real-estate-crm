<?php

namespace App\Providers;

use App\Observers\IndexableObserver;
use App\Services\Assistant\AssistantModel;
use App\Services\Assistant\ClaudeAssistantModel;
use App\Services\Assistant\FakeAssistantModel;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Services\Knowledge\VoyageEmbedder;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Embedder::class, fn () => $this->app->environment('testing')
            ? new FakeEmbedder
            : new VoyageEmbedder);

        // Tests never reach the real API: they get a scripted fake (tests replace it with their own queue).
        $this->app->singleton(AssistantModel::class, fn () => $this->app->environment('testing')
            ? new FakeAssistantModel
            : new ClaudeAssistantModel);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        foreach (KnowledgeIndexer::TYPES as $class) {
            $class::observe(IndexableObserver::class);
        }

        // Escape untrusted text (visitor names) so it cannot become Markdown links in mail.
        Markdown::withSecuredEncoding();

        // Public inquiry form: 5 per minute per IP.
        // AI assistant: 20 questions per minute per user.
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(20)->by('assistant:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('inquiries', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
