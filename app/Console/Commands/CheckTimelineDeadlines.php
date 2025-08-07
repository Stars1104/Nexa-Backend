<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignTimeline;
use App\Models\Contract;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;

class CheckTimelineDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timeline:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue timeline milestones and apply penalties';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking timeline deadlines...');

        // Get all overdue milestones that haven't been notified yet
        $overdueMilestones = CampaignTimeline::where('deadline', '<', now())
            ->where('status', '!=', 'completed')
            ->whereNull('delay_notified_at')
            ->with(['contract.creator', 'contract.brand', 'contract.offer'])
            ->get();

        $this->info("Found {$overdueMilestones->count()} overdue milestones");

        foreach ($overdueMilestones as $milestone) {
            $this->processOverdueMilestone($milestone);
        }

        // Check for creators with multiple overdue milestones (7-day suspension)
        $this->checkForSuspensions();

        $this->info('Timeline deadline check completed!');
    }

    /**
     * Process an overdue milestone
     */
    private function processOverdueMilestone(CampaignTimeline $milestone)
    {
        $contract = $milestone->contract;
        $creator = $contract->creator;
        $brand = $contract->brand;

        // Mark milestone as delayed
        $milestone->markAsDelayed();

        // Send notifications
        $this->sendOverdueNotifications($milestone, $contract, $creator, $brand);

        // Check if this is the creator's second overdue milestone
        $overdueCount = CampaignTimeline::whereHas('contract', function ($query) use ($creator) {
            $query->where('creator_id', $creator->id);
        })
        ->where('deadline', '<', now())
        ->where('status', '!=', 'completed')
        ->count();

        if ($overdueCount >= 2) {
            $this->applySuspension($creator);
        }

        $this->info("Processed overdue milestone {$milestone->id} for creator {$creator->name}");
    }

    /**
     * Send overdue notifications
     */
    private function sendOverdueNotifications($milestone, $contract, $creator, $brand)
    {
        // Notify creator
        $creatorMessage = "⚠️ Milestone '{$milestone->title}' está atrasado. 
        Prazo: " . $milestone->deadline->format('d/m/Y H:i') . "
        Contrato: {$contract->title}
        
        Se você não justificar o atraso, poderá receber uma penalidade de 7 dias sem novos convites.";

        // Create notification for creator
        \App\Models\Notification::create([
            'user_id' => $creator->id,
            'title' => 'Milestone Atrasado',
            'message' => $creatorMessage,
            'type' => 'timeline_overdue',
            'data' => [
                'milestone_id' => $milestone->id,
                'contract_id' => $contract->id,
                'deadline' => $milestone->deadline->toISOString(),
            ],
        ]);

        // Notify brand
        $brandMessage = "⚠️ Milestone '{$milestone->title}' está atrasado.
        Criador: {$creator->name}
        Prazo: " . $milestone->deadline->format('d/m/Y H:i') . "
        Contrato: {$contract->title}
        
        Você pode justificar o atraso para evitar penalidades ao criador.";

        // Create notification for brand
        \App\Models\Notification::create([
            'user_id' => $brand->id,
            'title' => 'Milestone Atrasado',
            'message' => $brandMessage,
            'type' => 'timeline_overdue',
            'data' => [
                'milestone_id' => $milestone->id,
                'contract_id' => $contract->id,
                'deadline' => $milestone->deadline->toISOString(),
            ],
        ]);

        // Send chat messages
        $this->sendChatMessages($milestone, $contract, $creator, $brand);
    }

    /**
     * Send chat messages about overdue milestone
     */
    private function sendChatMessages($milestone, $contract, $creator, $brand)
    {
        try {
            // Get the chat room for this contract
            $chatRoom = \App\Models\ChatRoom::whereHas('offers', function ($query) use ($contract) {
                $query->where('id', $contract->offer_id);
            })->first();

            if ($chatRoom) {
                // Send system message about overdue milestone
                \App\Models\Message::create([
                    'chat_room_id' => $chatRoom->id,
                    'sender_id' => $brand->id,
                    'message' => "⚠️ Milestone '{$milestone->title}' está atrasado desde " . 
                                $milestone->deadline->format('d/m/Y H:i') . ". 
                                Prazo para justificativa: 24 horas.",
                    'message_type' => 'system',
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send overdue milestone chat message', [
                'milestone_id' => $milestone->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Apply suspension to creator
     */
    private function applySuspension(User $creator)
    {
        // Check if creator is already suspended
        if ($creator->suspended_until && $creator->suspended_until->isFuture()) {
            return;
        }

        // Apply 7-day suspension
        $suspensionEnd = now()->addDays(7);
        
        $creator->update([
            'suspended_until' => $suspensionEnd,
            'suspension_reason' => 'Multiple overdue timeline milestones',
        ]);

        // Send suspension notification
        \App\Models\Notification::create([
            'user_id' => $creator->id,
            'title' => 'Conta Suspensa',
            'message' => "Sua conta foi suspensa por 7 dias devido a múltiplos milestones atrasados. 
            Suspensão até: " . $suspensionEnd->format('d/m/Y H:i'),
            'type' => 'account_suspended',
            'data' => [
                'suspended_until' => $suspensionEnd->toISOString(),
                'reason' => 'Multiple overdue timeline milestones',
            ],
        ]);

        $this->warn("Applied 7-day suspension to creator {$creator->name}");
    }

    /**
     * Check for creators with multiple overdue milestones
     */
    private function checkForSuspensions()
    {
        // Get creators with 2 or more overdue milestones
        $creatorsWithOverdue = \DB::table('campaign_timelines')
            ->join('contracts', 'campaign_timelines.contract_id', '=', 'contracts.id')
            ->join('users', 'contracts.creator_id', '=', 'users.id')
            ->where('campaign_timelines.deadline', '<', now())
            ->where('campaign_timelines.status', '!=', 'completed')
            ->where('users.role', 'creator')
            ->select('users.id', 'users.name', \DB::raw('COUNT(*) as overdue_count'))
            ->groupBy('users.id', 'users.name')
            ->havingRaw('COUNT(*) >= ?', [2])
            ->get();

        foreach ($creatorsWithOverdue as $creatorData) {
            $creator = User::find($creatorData->id);
            
            if ($creator && (!$creator->suspended_until || $creator->suspended_until->isPast())) {
                $this->applySuspension($creator);
            }
        }
    }
} 