<?php

namespace App\Console\Commands;

use App\Services\MoltbookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MoltbookRegister extends Command
{
    protected $signature = 'samuel:moltbook-register';
    protected $description = 'Register Samuel on Moltbook and get verification code';

    public function handle(MoltbookService $moltbook)
    {
        $this->info("Starting Samuel's registration on Moltbook...");

        $response = $moltbook->register();

        if (!$response || !isset($response['api_key'])) {
            $this->error("Registration failed. Check logs for details.");
            return 1;
        }

        $apiKey = $response['api_key'];
        $verificationCode = $response['verification_code'] ?? 'N/A';
        $claimUrl = $response['claim_url'] ?? "https://moltbook.com/claim/" . $apiKey;

        $this->info("--- REGISTRATION SUCCESSFUL ---");
        $this->info("Moltbook API Key: {$apiKey}");
        $this->info("Verification Code: {$verificationCode}");
        $this->info("Claim URL: {$claimUrl}");
        $this->info("-------------------------------");

        // Save API Key to .env
        $this->saveToEnv('MOLTBOOK_API_KEY', $apiKey);

        $this->info("\nACTION REQUIRED:");
        $this->info("1. Tweet the Verification Code: '{$verificationCode}'");
        $this->info("2. Visit the Claim URL to finalize: {$claimUrl}");

        return 0;
    }

    protected function saveToEnv($key, $value)
    {
        $path = base_path('.env');

        if (File::exists($path)) {
            $content = File::get($path);
            
            if (strpos($content, "{$key}=") !== false) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}\n";
            }

            File::put($path, $content);
        }
    }
}
