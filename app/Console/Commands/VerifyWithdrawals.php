<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Models\BankAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withdrawals:verify 
                            {--id= : Verify specific withdrawal by ID}
                            {--status= : Filter by status (pending, processing, completed, failed, cancelled)}
                            {--method= : Filter by withdrawal method}
                            {--start-date= : Start date for verification (Y-m-d)}
                            {--end-date= : End date for verification (Y-m-d)}
                            {--detailed : Show detailed verification information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify withdrawal accuracy and bank account details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting withdrawal verification...');

        $query = Withdrawal::with(['creator']);

        // Apply filters
        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        }

        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        if ($this->option('method')) {
            $query->where('withdrawal_method', $this->option('method'));
        }

        if ($this->option('start-date')) {
            $query->where('created_at', '>=', $this->option('start-date'));
        }

        if ($this->option('end-date')) {
            $query->where('created_at', '<=', $this->option('end-date') . ' 23:59:59');
        }

        $withdrawals = $query->orderBy('created_at', 'desc')->get();

        if ($withdrawals->isEmpty()) {
            $this->warn('No withdrawals found matching the criteria.');
            return 0;
        }

        $this->info("Found {$withdrawals->count()} withdrawal(s) to verify.");
        $this->newLine();

        $summary = [
            'total' => $withdrawals->count(),
            'passed' => 0,
            'failed' => 0,
            'pending' => 0,
        ];

        foreach ($withdrawals as $withdrawal) {
            $this->verifyWithdrawal($withdrawal, $summary);
        }

        $this->displaySummary($summary);

        return 0;
    }

    private function verifyWithdrawal(Withdrawal $withdrawal, array &$summary): void
    {
        $currentBankAccount = BankAccount::where('user_id', $withdrawal->creator_id)->first();
        $verificationStatus = $this->getOverallVerificationStatus($withdrawal, $currentBankAccount);

        // Update summary
        switch ($verificationStatus) {
            case 'passed':
                $summary['passed']++;
                break;
            case 'failed':
                $summary['failed']++;
                break;
            case 'pending':
                $summary['pending']++;
                break;
        }

        $this->displayWithdrawalVerification($withdrawal, $currentBankAccount, $verificationStatus);
    }

    private function displayWithdrawalVerification(Withdrawal $withdrawal, $currentBankAccount, string $status): void
    {
        $statusIcon = $this->getStatusIcon($status);
        $statusColor = $this->getStatusColor($status);

        $this->line("{$statusIcon} Withdrawal #{$withdrawal->id} - {$withdrawal->formatted_amount}");
        $this->line("   Creator: {$withdrawal->creator->name} ({$withdrawal->creator->email})");
        $this->line("   Method: {$withdrawal->withdrawal_method_label}");
        $this->line("   Status: {$withdrawal->status}");
        $this->line("   Created: {$withdrawal->created_at->format('Y-m-d H:i:s')}");

        if ($withdrawal->processed_at) {
            $this->line("   Processed: {$withdrawal->processed_at->format('Y-m-d H:i:s')}");
        }

        if ($withdrawal->transaction_id) {
            $this->line("   Transaction ID: {$withdrawal->transaction_id}");
        }

        // Bank account verification
        $bankDetailsMatch = $this->compareBankDetails($withdrawal, $currentBankAccount);
        $bankIcon = $bankDetailsMatch ? '✓' : '✗';
        $bankColor = $bankDetailsMatch ? 'green' : 'red';

        $this->line("   Bank Details Match: <fg={$bankColor}>{$bankIcon}</>");

        if ($this->option('detailed') && $withdrawal->withdrawal_details) {
            $this->displayDetailedBankInfo($withdrawal, $currentBankAccount);
        }

        $this->newLine();
    }

    private function displayDetailedBankInfo(Withdrawal $withdrawal, $currentBankAccount): void
    {
        $this->line("   <fg=yellow>Detailed Bank Information:</>");

        // Withdrawal bank details
        $withdrawalDetails = $this->extractBankDetailsFromWithdrawal($withdrawal);
        if ($withdrawalDetails) {
            $this->line("   <fg=blue>Withdrawal Bank Details:</>");
            $this->line("     Bank Code: {$withdrawalDetails['bank_code']}");
            $this->line("     Agency: {$withdrawalDetails['agencia']}-{$withdrawalDetails['agencia_dv']}");
            $this->line("     Account: {$withdrawalDetails['conta']}-{$withdrawalDetails['conta_dv']}");
            $this->line("     CPF: {$withdrawalDetails['cpf']}");
            $this->line("     Name: {$withdrawalDetails['name']}");
        }

        // Current bank account
        if ($currentBankAccount) {
            $this->line("   <fg=blue>Current Bank Account:</>");
            $this->line("     Bank Code: {$currentBankAccount->bank_code}");
            $this->line("     Agency: {$currentBankAccount->agencia}-{$currentBankAccount->agencia_dv}");
            $this->line("     Account: {$currentBankAccount->conta}-{$currentBankAccount->conta_dv}");
            $this->line("     CPF: {$currentBankAccount->cpf}");
            $this->line("     Name: {$currentBankAccount->name}");
        } else {
            $this->line("   <fg=red>No current bank account found</>");
        }
    }

    private function displaySummary(array $summary): void
    {
        $this->newLine();
        $this->info('=== Verification Summary ===');
        $this->line("Total Withdrawals: {$summary['total']}");
        $this->line("<fg=green>Passed: {$summary['passed']}</>");
        $this->line("<fg=red>Failed: {$summary['failed']}</>");
        $this->line("<fg=yellow>Pending: {$summary['pending']}</>");

        if ($summary['total'] > 0) {
            $passRate = round(($summary['passed'] / $summary['total']) * 100, 1);
            $this->line("Pass Rate: {$passRate}%");
        }
    }

    private function getStatusIcon(string $status): string
    {
        switch ($status) {
            case 'passed':
                return '✓';
            case 'failed':
                return '✗';
            case 'pending':
                return '⏳';
            default:
                return '?';
        }
    }

    private function getStatusColor(string $status): string
    {
        switch ($status) {
            case 'passed':
                return 'green';
            case 'failed':
                return 'red';
            case 'pending':
                return 'yellow';
            default:
                return 'white';
        }
    }

    private function extractBankDetailsFromWithdrawal(Withdrawal $withdrawal): ?array
    {
        if (!$withdrawal->withdrawal_details) {
            return null;
        }

        return [
            'bank_code' => $withdrawal->withdrawal_details['bank_code'] ?? null,
            'agencia' => $withdrawal->withdrawal_details['agencia'] ?? null,
            'agencia_dv' => $withdrawal->withdrawal_details['agencia_dv'] ?? null,
            'conta' => $withdrawal->withdrawal_details['conta'] ?? null,
            'conta_dv' => $withdrawal->withdrawal_details['conta_dv'] ?? null,
            'cpf' => $withdrawal->withdrawal_details['cpf'] ?? null,
            'name' => $withdrawal->withdrawal_details['name'] ?? null,
        ];
    }

    private function compareBankDetails(Withdrawal $withdrawal, $currentBankAccount): bool
    {
        if (!$currentBankAccount || !$withdrawal->withdrawal_details) {
            return false;
        }

        $withdrawalDetails = $this->extractBankDetailsFromWithdrawal($withdrawal);
        
        if (!$withdrawalDetails) {
            return false;
        }

        // Compare key fields
        $fieldsToCompare = ['bank_code', 'agencia', 'agencia_dv', 'conta', 'conta_dv', 'cpf'];
        
        foreach ($fieldsToCompare as $field) {
            if (($withdrawalDetails[$field] ?? '') !== ($currentBankAccount->$field ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function verifyWithdrawalAmount(Withdrawal $withdrawal): bool
    {
        if ($withdrawal->amount <= 0) {
            return false;
        }

        if ($withdrawal->amount > 1000000) {
            return false;
        }

        return true;
    }

    private function verifyProcessingTime(Withdrawal $withdrawal): bool
    {
        if (!$withdrawal->processed_at) {
            return true;
        }

        $processingTime = $withdrawal->created_at->diffInHours($withdrawal->processed_at);
        return $processingTime <= 72;
    }

    private function getOverallVerificationStatus(Withdrawal $withdrawal, $currentBankAccount): string
    {
        if ($withdrawal->status === 'pending' || $withdrawal->status === 'processing') {
            return 'pending';
        }

        if ($withdrawal->status === 'failed' || $withdrawal->status === 'cancelled') {
            return 'failed';
        }

        $amountCorrect = $this->verifyWithdrawalAmount($withdrawal);
        $bankDetailsMatch = $this->compareBankDetails($withdrawal, $currentBankAccount);
        $transactionIdValid = !empty($withdrawal->transaction_id);
        $processingTimeReasonable = $this->verifyProcessingTime($withdrawal);

        if ($amountCorrect && $bankDetailsMatch && $transactionIdValid && $processingTimeReasonable) {
            return 'passed';
        }

        return 'failed';
    }
} 