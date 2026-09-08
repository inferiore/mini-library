<?php

use App\Http\Controllers\Admin\RagDocumentController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('/demo-login/{role}', [DemoLoginController::class, 'store'])
    ->where('role', 'admin|librarian|member')
    ->name('demo-login');

Route::middleware('auth')->group(function () {
    // Placeholder until spec 003+ builds the real role-branched dashboard —
    // this just gives Fortify's post-login/post-registration redirect
    // (config('fortify.home') = '/dashboard') somewhere real to land.
    Route::inertia('/dashboard', 'dashboard')->name('dashboard');

    // Dedicated, invariant-sensitive inventory path (spec 004), kept off the
    // general book-edit form.
    Route::put('books/{book}/inventory', [InventoryController::class, 'update'])
        ->name('books.inventory.update');

    Route::resource('books', BookController::class);

    // Checkout / check-in (spec 005). Members see "My Loans"; staff see the
    // system-wide "All Loans" view (authorized in the controller/policy).
    // AI recommendations (spec 008) — a single-purpose JSON endpoint consumed
    // by the dashboard widget via client-side fetch. Rate-limited per user
    // because each call has real embedding/LLM cost.
    Route::post('recommendations', [RecommendationController::class, 'store'])
        ->middleware('throttle:recommendations')
        ->name('recommendations.store');

    Route::get('my-loans', [LoanController::class, 'myLoans'])->name('loans.mine');
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
    Route::put('loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
});

// Admin-only tooling (spec 009). The `role:admin` group is the reusable route
// gate for future admin sections; each action is also Policy-checked.
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('embeddings', [RagDocumentController::class, 'index'])->name('embeddings.index');
        Route::get('embeddings/{ragDocument}', [RagDocumentController::class, 'show'])->name('embeddings.show');
        Route::post('embeddings/{ragDocument}/retry', [RagDocumentController::class, 'retry'])->name('embeddings.retry');
        Route::post('embeddings/{ragDocument}/regenerate', [RagDocumentController::class, 'regenerate'])->name('embeddings.regenerate');
    });
