# Pagar.me Payment Integration Setup

This document describes the Pagar.me payment integration implementation for the Nexa application.

## Overview

The Pagar.me integration allows users to:

-   Add and manage credit card payment methods
-   Process payments for campaigns
-   View payment history
-   Securely store payment information

## Configuration

### Environment Variables

Add these environment variables to your `.env` file:

```env
# Pagar.me Configuration
PAGARME_PUBLIC_KEY=pk_5qnXpwbIGvtWZxzE
PAGARME_SECRET_KEY=sk_ca1c6ab72ce84f14853654e13dbbe25a
PAGARME_ACCOUNT_ID=acc_oGVvPdf56f3RvxDa
```

### Backend Configuration

The Pagar.me configuration is stored in `config/services.php`:

```php
'pagarme' => [
    'public_key' => env('PAGARME_PUBLIC_KEY'),
    'secret_key' => env('PAGARME_SECRET_KEY'),
    'account_id' => env('PAGARME_ACCOUNT_ID'),
],
```

## API Endpoints

### Payment Methods

-   `GET /api/payment/methods` - Get user's payment methods
-   `POST /api/payment/methods` - Create a new payment method
-   `DELETE /api/payment/methods/{cardId}` - Delete a payment method

### Payments

-   `POST /api/payment/process` - Process a payment
-   `GET /api/payment/history` - Get payment history

## Implementation Details

### Backend

1. **PaymentController** (`app/Http/Controllers/PaymentController.php`)

    - Handles all payment-related API requests
    - Integrates with Pagar.me API
    - Manages customer and card creation
    - Processes payments

2. **Key Features**:
    - Customer management (create/get customers)
    - Card tokenization and storage
    - Payment processing with order creation
    - Error handling and logging

### Frontend

1. **Payment API Service** (`nexa/src/api/payment/index.ts`)

    - TypeScript interfaces for payment data
    - API client methods for all payment operations

2. **Payment Component** (`nexa/src/components/brand/Payment.tsx`)
    - React component for payment method management
    - Real-time integration with Pagar.me API
    - Loading states and error handling
    - Toast notifications for user feedback

## Security Features

-   Card data is never stored locally
-   All sensitive data is handled by Pagar.me
-   API keys are stored securely in environment variables
-   HTTPS communication with Pagar.me API

## Testing

### Test Cards

Use these test card numbers for development:

-   **Visa**: 4111111111111111
-   **Mastercard**: 5555555555554444
-   **American Express**: 378282246310005

### Test CVV and Expiry

-   CVV: Any 3-digit number (e.g., 123)
-   Expiry: Any future date (e.g., 12/25)

## Error Handling

The integration includes comprehensive error handling:

-   API validation errors
-   Network connectivity issues
-   Pagar.me specific errors
-   User-friendly error messages

## Usage Examples

### Adding a Payment Method

```typescript
const paymentMethod = await paymentApi.createPaymentMethod({
    card_number: "4111111111111111",
    holder_name: "John Doe",
    exp_month: 12,
    exp_year: 2025,
    cvv: "123",
    isDefault: true,
});
```

### Processing a Payment

```typescript
const payment = await paymentApi.processPayment({
    amount: 100.0,
    card_id: "card_123456",
    description: "Campaign Payment",
    campaign_id: 1,
});
```

## Notes

-   Card editing is not supported by Pagar.me (cards must be deleted and re-added)
-   All amounts are processed in cents (multiplied by 100)
-   Customer IDs are mapped to user IDs for consistency
-   Payment history includes metadata for campaign tracking
