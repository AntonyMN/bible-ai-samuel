<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::domain('api.chatwithsamuel.org')->group(function () {
    require_once app_path('Models/PersonalAccessToken.php');
    config(['app.debug' => true]);

    Route::get('/debug/files', function () {
        $modelsDir = app_path('Models');
        return response()->json([
            'models_dir' => $modelsDir,
            'exists' => is_dir($modelsDir),
            'files' => is_dir($modelsDir) ? scandir($modelsDir) : [],
            'app_files' => scandir(app_path())
        ]);
    });

    Route::get('/ping', function () {
        $modelPath = app_path('Models/PersonalAccessToken.php');
        return response()->json([
            'status' => 'ok', 
            'time' => now()->toDateTimeString(), 
            'v' => 'debug_v5',
            'debug' => config('app.debug'),
            'model_exists' => file_exists($modelPath),
            'model_path' => $modelPath,
            'model_content' => file_exists($modelPath) ? file_get_contents($modelPath) : 'NOT_FOUND'
        ]);
    });

    // Authentication
    Route::post('/login', function (Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Login Attempt: ' . $request->email);
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                'device_name' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                    'errors' => ['email' => ['The provided credentials are incorrect.']]
                ], 422);
            }

            $token = $user->createToken($request->device_name)->plainTextToken;
            \Illuminate\Support\Facades\Log::info('Login Success for: ' . $request->email);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Login error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Server error during login: ' . $e->getMessage(),
            ], 500);
        }
    });

    Route::post('/register', function (Request $request) {
        try {
            \Illuminate\Support\Facades\Log::info('Register Attempt: ' . $request->email);
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'device_name' => 'required',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken($request->device_name)->plainTextToken;
            \Illuminate\Support\Facades\Log::info('Register Success for: ' . $request->email);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstError ?? 'Validation failed',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Register fatal error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Server error during registration: ' . $e->getMessage(),
            ], 500);
        }
    });

    // Guest Chat
    Route::post('/chat/send', [ChatController::class, 'send'])->middleware('usage_limit');

    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::get('/conversations', function (Request $request) {
            return \App\Models\Conversation::where('user_id', (string) $request->user()->id)
                ->orderBy('updated_at', 'desc')
                ->get();
        });

        Route::get('/conversations/{id}', [ChatController::class, 'show']);
        Route::patch('/conversations/{id}/title', [ChatController::class, 'updateTitle']);
        Route::post('/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out']);
        });
    });
});
