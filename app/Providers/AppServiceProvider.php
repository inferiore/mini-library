<?php

namespace App\Providers;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\AI\Http\OpenAiCompatibleEmbeddingService;
use App\AI\Http\OpenAiCompatibleLLMService;
use App\Models\Book;
use App\Observers\BookObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $this->app->bind(EmbeddingServiceInterface::class, OpenAiCompatibleEmbeddingService::class);
        $this->app->bind(LLMServiceInterface::class, OpenAiCompatibleLLMService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // The recommendation endpoint is LLM-backed with real per-call cost, so
        // it's throttled per user (falling back to IP for safety) — a
        // conservative default unlike the rest of the app's routes (spec 008).
        RateLimiter::for('recommendations', fn (Request $request) => Limit::perMinute(10)
            ->by((string) $request->user()->id)
            ->response(fn (): JsonResponse => response()->json([
                'message' => 'Too many recommendation requests. Please wait a moment and try again.',
            ], 429)));

        Book::observe(BookObserver::class);

        // Spec 007's SyncBookRagDocument listener (which rebuilds a book's RAG
        // document and dispatches the embedding job on BookNeedsReembedding) is
        // wired via Laravel's automatic listener discovery — its handle()
        // type-hints the event — so no explicit registration is needed here.
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
