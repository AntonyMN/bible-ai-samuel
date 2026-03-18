<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\ChatController;

$domain = parse_url(config('app.url'), PHP_URL_HOST);

// Admin Subdomain
Route::domain('admin.' . $domain)->group(function () {
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
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

    require __DIR__.'/auth.php';

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('/user/tts-settings', [ChatController::class, 'updateTtsSettings'])->name('user.tts-settings');
        Route::post('/user/bible-version', [ChatController::class, 'updateBibleVersion'])->name('user.bible-version');
        Route::patch('/conversations/{id}/title', [ChatController::class, 'updateTitle'])->name('chat.update-title');
    });
});

