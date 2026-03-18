<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KofiWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->input('data');
        
        if (!$payload) {
            Log::warning('Kofi Webhook: Empty payload', ['all' => $request->all()]);
            return response()->json(['message' => 'Empty payload'], 400);
        }

        // Ko-fi verification (if token is set)
        $expectedToken = config('services.kofi.verification_token') ?: env('KOFI_VERIFICATION_TOKEN');
        $receivedToken = $request->input('verification_token');

        if ($expectedToken && $receivedToken !== $expectedToken) {
            Log::error('Kofi Webhook: Invalid verification token', [
                'received' => $receivedToken,
                'expected' => $expectedToken
            ]);
            return response()->json(['message' => 'Invalid token'], 403);
        }

        $email = $payload['email'] ?? null;
        $type = $request->input('type');

        if ($type === 'Donation' && $email) {
            $user = User::where('email', $email)->first();
            
            if ($user) {
                $user->update([
                    'is_donor' => true,
                    'donor_thanked_at' => null, // Reset so they get thanked in next chat
                ]);
                Log::info("Kofi Webhook: User {$email} marked as donor.");
            } else {
                Log::info("Kofi Webhook: Donation from {$email} received, but user not found in database.");
            }
        }

        return response()->json(['message' => 'Webhook received']);
    }
}
