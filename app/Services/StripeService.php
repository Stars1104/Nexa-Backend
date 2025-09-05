<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Subscription;

class StripeService
{
    public function __construct()
    {
        // Set your Stripe secret key
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a one-time payment in BRL
     */
    public function createPaymentBRL(float $amount, string $paymentMethodId)
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => intval($amount * 100), // convert to cents
            'currency' => 'brl', // Brazilian Real
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'payment_method_types' => ['card'], // Add 'boleto' or 'pix' if needed
        ]);

        return $paymentIntent;
    }

    /**
     * Create a subscription in BRL
     */
    public function createSubscriptionBRL(string $customerId, string $priceId)
    {
        $subscription = Subscription::create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        return $subscription;
    }
}
