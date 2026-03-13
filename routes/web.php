<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\ChatController;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
Route::get('/chat/show/{id}', [ChatController::class, 'show'])->name('chat.show');



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/user/tts-settings', [ChatController::class, 'updateTtsSettings'])->name('user.tts-settings');
    Route::post('/user/bible-version', [ChatController::class, 'updateBibleVersion'])->name('user.bible-version');
    Route::patch('/conversations/{id}/title', [ChatController::class, 'updateTitle'])->name('chat.update-title');
});

require __DIR__.'/auth.php';
