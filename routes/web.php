<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\ChatController;

$domain = parse_url(config('app.url'), PHP_URL_HOST);

// Blog Subdomain
Route::domain('blog.' . $domain)->group(function () {
    Route::get('/', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
    Route::get('/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');
});

// Admin Subdomain
Route::domain('admin.' . $domain)->group(function () {
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
        
        // Blog Management
        Route::prefix('blog')->name('admin.blog.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\BlogController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [\App\Http\Controllers\Admin\BlogController::class, 'edit'])->name('edit');
            Route::patch('/{id}', [\App\Http\Controllers\Admin\BlogController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\BlogController::class, 'destroy'])->name('destroy');
        });
    });
});

// Chat Subdomain
Route::domain('chat.' . $domain)->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/show/{id}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/send', [ChatController::class, 'send'])->name('chat.send')->middleware('usage_limit');
});

// Root Domain
Route::domain($domain)->group(function () {
    // Landing Page
    Route::get('/', function () {
        return Inertia::render('Landing');
    })->name('landing');

    // Privacy Policy
    Route::get('/privacy', function () {
        return Inertia::render('Privacy');
    })->name('privacy');
});

// Authentication Routes (available on all domains)
require __DIR__.'/auth.php';

// Common Authenticated User Routes (available on all domains)
Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Chat Settings & Preferences
    Route::post('/user/tts-settings', [ChatController::class, 'updateTtsSettings'])->name('user.tts-settings');
    Route::post('/user/bible-version', [ChatController::class, 'updateBibleVersion'])->name('user.bible-version');
    Route::post('/user/model', [ChatController::class, 'updateModel'])->name('user.model');
    Route::patch('/conversations/{id}/title', [ChatController::class, 'updateTitle'])->name('chat.update-title');
});

