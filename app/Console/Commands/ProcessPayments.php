<?php

namespace App\Console\Commands;

use App\Models\JobPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending job payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payment processing...');

        $pendingPayments = JobPayment::where('status', 'pending')->get();

        $processed = 0;
        $failed = 0;

        foreach ($pendingPayments as $payment) {
            try {
                if ($payment->process()) {
                    $processed++;
                    $this->info("Payment {$payment->id} processed successfully.");
                } else {
                    $failed++;
                    $this->error("Payment {$payment->id} failed to process.");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("Payment {$payment->id} failed with error: " . $e->getMessage());
                
                Log::error('Payment processing error', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Payment processing completed. Processed: {$processed}, Failed: {$failed}");

        return 0;
    }
} 