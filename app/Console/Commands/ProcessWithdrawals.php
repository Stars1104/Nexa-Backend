<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withdrawals:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending withdrawal requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting withdrawal processing...');

        $pendingWithdrawals = Withdrawal::where('status', 'pending')->get();

        $processed = 0;
        $failed = 0;

        foreach ($pendingWithdrawals as $withdrawal) {
            try {
                if ($withdrawal->process()) {
                    $processed++;
                    $this->info("Withdrawal {$withdrawal->id} processed successfully.");
                } else {
                    $failed++;
                    $this->error("Withdrawal {$withdrawal->id} failed to process.");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("Withdrawal {$withdrawal->id} failed with error: " . $e->getMessage());
                
                Log::error('Withdrawal processing error', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Withdrawal processing completed. Processed: {$processed}, Failed: {$failed}");

        return 0;
    }
} 