<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignTimeline;
use App\Models\Contract;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckMilestoneDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'milestones:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check milestone deadlines and send automatic delay warnings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking milestone deadlines...');

        try {
            // Find overdue milestones that haven't been notified yet
            $overdueMilestones = CampaignTimeline::where('deadline', '<', now())
                ->where('status', 'pending')
                ->where('is_delayed', false)
                ->whereNull('delay_notified_at')
                ->with(['contract.creator', 'contract.brand'])
                ->get();

            if ($overdueMilestones->isEmpty()) {
                $this->info('No overdue milestones found.');
                return 0;
            }

            $this->info("Found {$overdueMilestones->count()} overdue milestones.");

            $warningsSent = 0;
            $penaltiesApplied = 0;

            foreach ($overdueMilestones as $milestone) {
                try {
                    $this->info("Processing milestone: {$milestone->title} (ID: {$milestone->id})");

                    // Send delay warning notification
                    NotificationService::notifyCreatorOfMilestoneDelay($milestone);
                    
                    // Mark as notified
                    $milestone->update([
                        'delay_notified_at' => now(),
                        'is_delayed' => true,
                    ]);

                    $warningsSent++;
                    $this->info("✓ Warning sent for milestone: {$milestone->title}");

                    // Check if this is the second warning (7 days after first)
                    $this->checkAndApplyPenalties($milestone);

                } catch (\Exception $e) {
                    $this->error("Failed to process milestone {$milestone->id}: {$e->getMessage()}");
                    Log::error('Failed to process milestone in command', [
                        'milestone_id' => $milestone->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("✓ Processed {$overdueMilestones->count()} milestones");
            $this->info("✓ Warnings sent: {$warningsSent}");
            $this->info("✓ Penalties applied: {$penaltiesApplied}");

            return 0;

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('CheckMilestoneDeadlines command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Check and apply penalties for overdue milestones
     */
    private function checkAndApplyPenalties(CampaignTimeline $milestone)
    {
        try {
            // Check if this milestone has been overdue for more than 7 days
            $daysOverdue = $milestone->getDaysOverdue();
            
            if ($daysOverdue >= 7 && !$milestone->penalty_applied) {
                $this->info("Applying penalty for milestone: {$milestone->title} (overdue for {$daysOverdue} days)");
                
                // Apply penalty to creator
                $creator = $milestone->contract->creator;
                
                // Mark creator as penalized (7 days without new invitations)
                $creator->update([
                    'penalty_until' => now()->addDays(7),
                    'penalty_reason' => 'Milestone overdue for more than 7 days',
                    'penalty_milestone_id' => $milestone->id,
                ]);

                // Mark milestone as penalty applied
                $milestone->update([
                    'penalty_applied' => true,
                    'penalty_applied_at' => now(),
                ]);

                // Send penalty notification
                $this->sendPenaltyNotification($milestone, $creator);
                
                $this->info("✓ Penalty applied to creator: {$creator->name}");
            }

        } catch (\Exception $e) {
            $this->error("Failed to apply penalty for milestone {$milestone->id}: {$e->getMessage()}");
            Log::error('Failed to apply penalty', [
                'milestone_id' => $milestone->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send penalty notification to creator
     */
    private function sendPenaltyNotification(CampaignTimeline $milestone, User $creator)
    {
        try {
            // Create penalty notification
            \App\Models\Notification::create([
                'user_id' => $creator->id,
                'type' => 'milestone_penalty',
                'title' => '🚫 Penalidade Aplicada - 7 Dias Sem Convites',
                'message' => "Devido ao atraso no milestone '{$milestone->title}', você recebeu uma penalidade de 7 dias sem novos convites para campanhas.",
                'data' => [
                    'milestone_id' => $milestone->id,
                    'contract_id' => $milestone->contract_id,
                    'contract_title' => $milestone->contract->title,
                    'penalty_duration' => 7,
                    'penalty_until' => now()->addDays(7)->toISOString(),
                    'penalty_reason' => 'Milestone overdue for more than 7 days',
                ],
                'read_at' => null,
            ]);

            // Send email notification
            try {
                // You can create a specific email template for penalties
                // Mail::to($creator->email)->send(new MilestonePenaltyEmail($milestone));
            } catch (\Exception $emailError) {
                Log::error('Failed to send penalty email', [
                    'creator_id' => $creator->id,
                    'error' => $emailError->getMessage()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send penalty notification', [
                'creator_id' => $creator->id,
                'milestone_id' => $milestone->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 