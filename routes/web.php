<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\ChatController;

// Chat System Routes
Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::get('/chat/show/{id}', [ChatController::class, 'show'])->name('chat.show');
Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send')->middleware('usage_limit');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/user/tts-settings', [ChatController::class, 'updateTtsSettings'])->name('user.tts-settings');
    Route::post('/user/bible-version', [ChatController::class, 'updateBibleVersion'])->name('user.bible-version');
    Route::patch('/conversations/{id}/title', [ChatController::class, 'updateTitle'])->name('chat.update-title');
});

// Admin Routes (Global)
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
});

// Landing Page fallback (if accessed without chat subdomain)
Route::get('/landing', function () {
    return Inertia::render('Landing');
})->name('landing');

// Fallback for local development without subdomains
if (app()->environment('local')) {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.local');
}

require __DIR__.'/auth.php';
