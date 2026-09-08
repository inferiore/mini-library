<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\InventoryController;
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
});
