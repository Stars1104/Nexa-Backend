# Payment Simulation Mode

This document explains how to use the payment simulation mode in the Nexa platform.

## Overview

The payment simulation mode allows you to temporarily disable real payment processing while maintaining the complete payment flow experience. All payments are processed through database manipulation instead of actual API calls to Pagar.me.

## How to Enable Simulation Mode

### 1. Environment Configuration

Add the following to your `.env` file:

```bash
# Enable payment simulation mode
PAGARME_SIMULATION_MODE=true
```

### 2. Configuration Details

The simulation mode is configured in `config/services.php`:

```php
'pagarme' => [
    'api_key' => env('PAGARME_API_KEY'),
    'secret_key' => env('PAGARME_SECRET_KEY'),
    'encryption_key' => env('PAGARME_ENCRYPTION_KEY'),
    'webhook_secret' => env('PAGARME_WEBHOOK_SECRET'),
    'environment' => env('PAGARME_ENVIRONMENT', 'sandbox'),
    'account_id' => env('PAGARME_ACCOUNT_ID'),
    'simulation_mode' => env('PAGARME_SIMULATION_MODE', false), // Enable payment simulation
],
```

## What Gets Simulated

### 1. Subscription Payments
- Creator premium subscription payments
- All validation and business logic preserved
- Database records created as if payment succeeded
- User premium status updated

### 2. Contract Payments
- Brand contract payments
- Automatic payment processing
- Creator balance updates
- Platform fee calculations

### 3. Account Payments
- Payments using account_id authentication
- Balance-based payments
- Premium status updates

### 4. Withdrawal Processing
- Creator withdrawal requests
- Multiple withdrawal methods (PIX, Bank Transfer, Pagar.me)
- Balance updates and transaction records

## Simulation Features

### 1. Realistic Behavior
- **API Delays**: Simulated response times (0.2-0.5 seconds)
- **Transaction IDs**: Generated with `SIM_` prefix
- **Status Updates**: All payments marked as 'paid'
- **Database Records**: Complete transaction history

### 2. Error Simulation (Optional)
- 5% chance of simulated errors for testing
- Configurable error rates
- Realistic error messages

### 3. Logging
- Comprehensive simulation logging
- Clear identification of simulated transactions
- Debug information for troubleshooting

## Database Changes

When simulation mode is enabled:

1. **Transaction Records**: Created with `simulation: true` flag
2. **Payment Data**: Includes simulation metadata
3. **User Status**: Premium status and balances updated
4. **Contract Status**: Workflow statuses updated
5. **Creator Balances**: Withdrawal and earning records created

## API Response Changes

Simulated payments return additional fields:

```json
{
    "success": true,
    "message": "Payment processed successfully (SIMULATION)",
    "simulation": true,
    "transaction": {
        "id": "SIM_1234567890_123_4567",
        "status": "paid"
    }
}
```

## Testing

### 1. Enable Simulation Mode
```bash
# In your .env file
PAGARME_SIMULATION_MODE=true
```

### 2. Test Payment Flows
- All payment forms work identically
- No real money is charged
- All validations and business logic preserved
- Database records created normally

### 3. Check Logs
Look for simulation indicators in logs:
```
[INFO] SIMULATION: Processing subscription payment
[INFO] SIMULATION: Payment processed successfully
```

## Disabling Simulation Mode

To return to real payment processing:

1. Set `PAGARME_SIMULATION_MODE=false` in `.env`
2. Ensure valid Pagar.me API credentials
3. Restart your application

## Benefits

✅ **Zero UI Changes**: Users see identical experience  
✅ **Complete Flow Preservation**: All forms and processes work  
✅ **Easy Rollback**: Simple environment variable toggle  
✅ **Testing Friendly**: Perfect for development and testing  
✅ **Database Integrity**: All records created as if real payments occurred  
✅ **No Real Charges**: Safe for testing with real payment forms  

## Files Modified

- `config/services.php` - Added simulation mode configuration
- `app/Services/PaymentSimulator.php` - New simulation service
- `app/Http/Controllers/PaymentController.php` - Added simulation logic
- `app/Http/Controllers/ContractPaymentController.php` - Added simulation logic
- `app/Services/AutomaticPaymentService.php` - Added simulation logic

## Troubleshooting

### Simulation Not Working
1. Check `PAGARME_SIMULATION_MODE=true` in `.env`
2. Clear config cache: `php artisan config:clear`
3. Check logs for simulation mode activation

### Database Issues
1. Ensure all migrations are run
2. Check transaction table structure
3. Verify user and contract relationships

### Logging Issues
1. Check log file permissions
2. Verify logging configuration
3. Look for simulation-specific log entries
