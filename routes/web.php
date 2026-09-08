<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\DemoLoginController;
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

    Route::resource('books', BookController::class);
});
