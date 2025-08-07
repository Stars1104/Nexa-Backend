<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CampaignTimeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'milestone_type',
        'title',
        'description',
        'deadline',
        'completed_at',
        'status',
        'comment',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'justification',
        'is_delayed',
        'delay_notified_at',
        'extension_days',
        'extension_reason',
        'extended_at',
        'extended_by',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
        'delay_notified_at' => 'datetime',
        'is_delayed' => 'boolean',
        'extended_at' => 'datetime',
    ];

    // Relationships
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    // Milestone types
    const MILESTONE_TYPES = [
        'script_submission' => 'Envio do Roteiro',
        'script_approval' => 'Aprovação do Roteiro',
        'video_submission' => 'Envio do Vídeo',
        'final_approval' => 'Aprovação Final',
    ];

    // Status types
    const STATUSES = [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'delayed' => 'Atrasado',
        'completed' => 'Concluído',
    ];

    // Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDelayed(): bool
    {
        return $this->status === 'delayed' || $this->is_delayed;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverdue(): bool
    {
        return $this->deadline->isPast() && !$this->isCompleted();
    }

    public function getDaysUntilDeadline(): int
    {
        return max(0, now()->diffInDays($this->deadline, false));
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        return abs(now()->diffInDays($this->deadline, false));
    }

    public function getStatusColor(): string
    {
        if ($this->isCompleted()) {
            return 'green';
        }
        if ($this->isApproved()) {
            return 'blue';
        }
        if ($this->isDelayed() || $this->isOverdue()) {
            return 'red';
        }
        return 'yellow';
    }

    public function getStatusIcon(): string
    {
        if ($this->isCompleted()) {
            return '🟢';
        }
        if ($this->isApproved()) {
            return '🟢';
        }
        if ($this->isDelayed() || $this->isOverdue()) {
            return '🔴';
        }
        return '🟡';
    }

    public function getMilestoneIcon(): string
    {
        switch ($this->milestone_type) {
            case 'script_submission':
                return '📝';
            case 'script_approval':
                return '✅';
            case 'video_submission':
                return '🎥';
            case 'final_approval':
                return '🏆';
            default:
                return '📋';
        }
    }

    public function canBeCompleted(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    public function canBeApproved(): bool
    {
        return $this->isPending() && $this->file_path;
    }

    public function canUploadFile(): bool
    {
        return $this->isPending() && in_array($this->milestone_type, ['script_submission', 'video_submission']);
    }

    public function canRequestApproval(): bool
    {
        return $this->isPending() && in_array($this->milestone_type, ['script_approval', 'final_approval']);
    }

    public function canJustifyDelay(): bool
    {
        return $this->isDelayed() && !$this->justification;
    }

    public function markAsCompleted(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return true;
    }

    public function markAsApproved(string $comment = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'comment' => $comment,
        ]);

        return true;
    }

    public function markAsDelayed(string $justification = null): bool
    {
        $this->update([
            'status' => 'delayed',
            'is_delayed' => true,
            'justification' => $justification,
            'delay_notified_at' => now(),
        ]);

        return true;
    }

    public function uploadFile(string $filePath, string $fileName, string $fileSize, string $fileType): bool
    {
        if (!$this->canUploadFile()) {
            return false;
        }

        $this->update([
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_type' => $fileType,
        ]);

        return true;
    }

    public function justifyDelay(string $justification): bool
    {
        if (!$this->canJustifyDelay()) {
            return false;
        }

        $this->update([
            'justification' => $justification,
        ]);

        return true;
    }

    /**
     * Extend timeline deadline
     */
    public function extendTimeline(int $days, string $reason, int $extendedBy): bool
    {
        $this->update([
            'extension_days' => $this->extension_days + $days,
            'extension_reason' => $reason,
            'extended_at' => now(),
            'extended_by' => $extendedBy,
            'deadline' => $this->deadline->addDays($days),
            'is_delayed' => false, // Reset delay status
            'status' => $this->status === 'delayed' ? 'pending' : $this->status,
        ]);

        return true;
    }

    /**
     * Check if timeline has been extended
     */
    public function isExtended(): bool
    {
        return $this->extension_days > 0;
    }

    /**
     * Get total extension days
     */
    public function getTotalExtensionDays(): int
    {
        return $this->extension_days;
    }

    /**
     * Get extended deadline (original + extensions)
     */
    public function getExtendedDeadline(): \Carbon\Carbon
    {
        return $this->deadline;
    }

    /**
     * Check if can be extended (only brand can extend)
     */
    public function canBeExtended(): bool
    {
        return $this->contract->brand_id === auth()->id();
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown size';
        }

        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getFormattedDeadlineAttribute(): string
    {
        return $this->deadline->format('M d, Y H:i');
    }

    public function getFormattedCompletedAtAttribute(): string
    {
        return $this->completed_at ? $this->completed_at->format('M d, Y H:i') : null;
    }

    public function getFormattedDelayNotifiedAtAttribute(): string
    {
        return $this->delay_notified_at ? $this->delay_notified_at->format('M d, Y H:i') : null;
    }
} 