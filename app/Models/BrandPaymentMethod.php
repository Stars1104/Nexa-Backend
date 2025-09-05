<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class BrandPaymentMethod
 *
 * Represents a payment method saved by a brand/user (Stripe integration).
 *
 * @property int $id
 * @property int $brand_id
 * @property string $customer_id
 * @property string $payment_method_id
 * @property string|null $card_holder_name
 * @property string|null $card_brand
 * @property string|null $card_last4
 * @property int|null $card_exp_month
 * @property int|null $card_exp_year
 * @property bool $is_default
 * @property bool $is_active
 */
class BrandPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'customer_id',
        'payment_method_id',
        'card_holder_name',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
    ];

    /**
     * Get the user that owns this payment method.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'brand_id');
    }

    /**
     * Scope to get only active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Set this payment method as default and unset others for the same user.
     */
    public function setAsDefault(): void
    {
        static::where('brand_id', $this->brand_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get masked card number for display.
     */
    public function getMaskedCardNumberAttribute(): string
    {
        return $this->card_last4 ? '**** **** **** ' . $this->card_last4 : '**** **** **** ****';
    }

    /**
     * Get formatted card info for display (e.g., Visa •••• 4242).
     */
    public function getFormattedCardInfoAttribute(): string
    {
        $brand = $this->card_brand ? ucfirst($this->card_brand) : 'Card';
        $last4 = $this->card_last4 ?? '****';
        return "{$brand} •••• {$last4}";
    }
}
