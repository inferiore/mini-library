<?php

namespace App\Providers;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\AI\Http\OpenAiCompatibleEmbeddingService;
use App\AI\Http\OpenAiCompatibleLLMService;
use App\Models\Book;
use App\Observers\BookObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
