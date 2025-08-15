<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\EmailVerificationService;
use App\Models\User;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email} {--provider=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration with different providers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $provider = $this->option('provider');

        $this->info("Testing email configuration for: {$email}");
        $this->info("Provider: {$provider}");
        $this->newLine();

        // Create a test user
        $user = new User();
        $user->email = $email;
        $user->name = 'Test User';

        // Test different providers
        if ($provider === 'all' || $provider === 'ses') {
            $this->testSES($user);
        }

        if ($provider === 'all' || $provider === 'smtp') {
            $this->testSMTP($user);
        }

        if ($provider === 'all' || $provider === 'log') {
            $this->testLog($user);
        }

        $this->info('Email testing completed. Check logs for details.');
    }

    /**
     * Test AWS SES
     */
    private function testSES(User $user)
    {
        $this->info('Testing AWS SES...');
        
        try {
            $result = EmailVerificationService::sendVerificationEmail($user);
            
            if ($result) {
                $this->info('✅ SES test successful');
            } else {
                $this->error('❌ SES test failed');
            }
        } catch (\Exception $e) {
            $this->error('❌ SES test error: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Test SMTP
     */
    private function testSMTP(User $user)
    {
        $this->info('Testing SMTP...');
        
        try {
            // Configure mail to use SMTP
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => env('MAIL_HOST', 'smtp.gmail.com'),
                'mail.mailers.smtp.port' => env('MAIL_PORT', 587),
                'mail.mailers.smtp.encryption' => env('MAIL_ENCRYPTION', 'tls'),
                'mail.mailers.smtp.username' => env('MAIL_USERNAME'),
                'mail.mailers.smtp.password' => env('MAIL_PASSWORD'),
                'mail.from.address' => env('MAIL_FROM_ADDRESS', 'noreply@nexacreators.com.br'),
                'mail.from.name' => env('MAIL_FROM_NAME', 'Nexa'),
            ]);
            
            Mail::raw('This is a test email from Nexa Platform', function($message) use ($user) {
                $message->to($user->email)
                        ->subject('Test Email - Nexa Platform');
            });
            
            $this->info('✅ SMTP test successful');
        } catch (\Exception $e) {
            $this->error('❌ SMTP test error: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Test Log
     */
    private function testLog(User $user)
    {
        $this->info('Testing Log...');
        
        try {
            // Actually test our EmailVerificationService
            $result = EmailVerificationService::sendVerificationEmail($user);
            
            if ($result['success']) {
                $this->info('✅ Log test successful - New template used');
                $this->info('Method: ' . $result['method']);
                $this->info('Message: ' . $result['message']);
                if ($result['verification_url']) {
                    $this->info('Verification URL: ' . $result['verification_url']);
                }
            } else {
                $this->error('❌ Log test failed: ' . $result['message']);
            }
        } catch (\Exception $e) {
            $this->error('❌ Log test error: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
} 