<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'samuel:test-email {email}';
    protected $description = 'Send a test email to verify SMTP settings';

    public function handle()
    {
        $recipient = $this->argument('email');
        $this->info("Attempting to send a test email to: {$recipient}");

        try {
            Mail::raw('Hello! This is a test email from Samuel AI to verify that the SMTP settings are working correctly. God bless!', function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('Samuel AI - SMTP Test');
            });

            $this->info("Test email sent successfully!");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            return 1;
        }
    }
}
