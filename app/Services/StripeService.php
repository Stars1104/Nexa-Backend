<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\SetupIntent;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\PaymentMethod;
use Stripe\Account;
use Stripe\AccountLink;

class StripeService
{
    public function __construct()
    {
        // Set your Stripe secret key
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCustomerForUser($user)
    {
        $customer = Customer::create([
            'email' => $user->email,
            'name'  => $user->name,
        ]);

        // // Save the Stripe customer ID in your database
        // $user->stripe_customer_id = $customer->id;
        // $user->save();

        return $customer->id;
    }

    public function createSetupIntent($request)
    {
        // Ensure you already created a Stripe Customer for this user
        $customerId = $request->input('customerId');

        $setupIntent = SetupIntent::create([
            'customer' => $customerId,
        ]);

        return response()->json([
            'clientSecret' => $setupIntent->client_secret,
        ]);
    }

    public function getCardInfoFromPaymentMethod($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::retrieve($paymentMethodId);

        if ($paymentMethod && $paymentMethod->type === 'card') {
            return response()->json([
                'brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
                'exp_month' => $paymentMethod->card->exp_month,
                'exp_year' => $paymentMethod->card->exp_year,
            ]);
        }

        return null;
    }

    /**
     * Create a payment intent using a saved card
     */
    public function createPaymentIntentFromSavedCard($paymentData)
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => intval($paymentData['amount'] * 100), // convert to centavos (1BRL = 100 centavos)
            'currency' => 'brl',
            'customer' => $paymentData['customer_id'],
            'payment_method' => $paymentData['payment_method_id'],
            'off_session' => true,
            'confirm' => true,
            'description' => "Payment for contract #{$paymentData['contract_id']}",
            'metadata' => [
                'contract_id' => $paymentData['contract_id'],
                'brand_id' => $paymentData['brand_id'],
                'creator_id' => $paymentData['creator_id'],
            ],
        ]);

        return $paymentIntent;
    }

    public function createConnectAccountForDeveloper($creatorEmail)
    {
        $account = Account::create([
            'type' => 'express',
            'country' => 'BR',
            'email' => $creatorEmail,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);
        return $account;
    }

    public function createAccountLink($accountId)
    {
        $accountLink = AccountLink::create([
            'account' => $accountId,
            'refresh_url' => route('stripe.onboarding.refresh'),
            'return_url' => route('stripe.onboarding.complete'),
            'type' => 'account_onboarding',
        ]);

        return $accountLink;
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
