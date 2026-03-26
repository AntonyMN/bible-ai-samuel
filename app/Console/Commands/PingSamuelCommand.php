<?php

namespace App\Console\Commands;

use App\Services\AiServiceInterface;
use Illuminate\Console\Command;

class PingSamuelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'samuel:ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pings Samuel AI to ensure the service is running and responsive.';

    /**
     * Execute the console command.
     */
    public function handle(AiServiceInterface $aiService)
    {
        $this->info('Pinging Samuel AI...');

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant. Reply exactly with "PONG"'],
                ['role' => 'user', 'content' => 'PING']
            ];

            // Use a specific model if needed, otherwise it defaults
            $response = $aiService->chat($messages);
            
            $content = $response['message']['content'] ?? '';

            if (empty($content)) {
                throw new \Exception('Empty response received from Samuel AI.');
            }

            $this->info('Samuel AI responded successfully: ' . $content);
        } catch (\Exception $e) {
            $this->error('Samuel AI ping failed: ' . $e->getMessage());

            // Send Email Alert
            \Illuminate\Support\Facades\Mail::to('antonymuriuki7@gmail.com')
                ->send(new \App\Mail\SamuelDowntimeAlert($e->getMessage()));
                
            $this->info('Downtime alert email sent.');
        }
    }
}
