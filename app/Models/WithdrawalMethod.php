<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'min_amount',
        'max_amount',
        'processing_time',
        'fee',
        'is_active',
        'required_fields',
        'field_config',
        'sort_order',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'is_active' => 'boolean',
        'required_fields' => 'array',
        'field_config' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Get active withdrawal methods ordered by sort order
     */
    public static function getActiveMethods()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get method by code
     */
    public static function findByCode(string $code)
    {
        return static::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get formatted fee
     */
    public function getFormattedFeeAttribute(): string
    {
        return 'R$ ' . number_format($this->fee, 2, ',', '.');
    }

    /**
     * Get formatted min amount
     */
    public function getFormattedMinAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->min_amount, 2, ',', '.');
    }

    /**
     * Get formatted max amount
     */
    public function getFormattedMaxAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->max_amount, 2, ',', '.');
    }

    /**
     * Check if amount is within limits
     */
    public function isAmountValid(float $amount): bool
    {
        return $amount >= $this->min_amount && $amount <= $this->max_amount;
    }

    /**
     * Get required fields for this method
     */
    public function getRequiredFields(): array
    {
        return $this->required_fields ?? [];
    }

    /**
     * Get field configuration for this method
     */
    public function getFieldConfig(): array
    {
        return $this->field_config ?? [];
    }

    /**
     * Validate withdrawal details for this method
     */
    public function validateWithdrawalDetails(array $details): array
    {
        $errors = [];
        $requiredFields = $this->getRequiredFields();

        foreach ($requiredFields as $field) {
            if (!isset($details[$field]) || empty($details[$field])) {
                $errors[] = "O campo '{$field}' é obrigatório para {$this->name}";
            }
        }

        return $errors;
    }
}
