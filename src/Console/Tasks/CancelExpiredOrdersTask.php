<?php

namespace Ingenius\Payforms\Console\Tasks;

use Illuminate\Support\Facades\Log;
use Ingenius\Core\Interfaces\IWithPayment;
use Ingenius\Core\Interfaces\ScheduledTaskInterface;
use Ingenius\Payforms\Models\PaymentTransaction;

/**
 * Cancel orders with expired payment transactions
 *
 * This task runs periodically to find payables (orders, etc.) that have
 * expired payment transactions and calls their onPaymentExpired() method.
 */
class CancelExpiredOrdersTask implements ScheduledTaskInterface
{
    /**
     * Run every hour to check for expired payments
     */
    public function schedule(): string
    {
        return 'hourly';
    }

    /**
     * Process expired payment transactions
     */
    public function handle(): void
    {
        Log::info('Starting CancelExpiredOrdersTask to process expired payment transactions');
        // Find all expired payment transactions
        $expiredTransactions = PaymentTransaction::expired()
            ->whereNotNull('payable_id')
            ->whereNotNull('payable_type')
            ->with('payable')
            ->get();

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($expiredTransactions as $transaction) {
            $payable = $transaction->payable;

            // Skip if payable doesn't exist
            if (!$payable) {
                $skippedCount++;
                continue;
            }

            // Skip if payable doesn't implement IWithPayment
            if (!($payable instanceof IWithPayment)) {
                Log::warning('Payable does not implement IWithPayment interface', [
                    'transaction_id' => $transaction->id,
                    'payable_type' => get_class($payable),
                    'payable_id' => $payable->id,
                ]);
                $skippedCount++;
                continue;
            }

            // Check if payable is already in a terminal state
            // Using property_exists and dynamic access to maintain package isolation
            // @phpstan-ignore-next-line - Dynamic property access after existence check
            if ($payable->status) {
                $terminalStatuses = ['completed', 'cancelled', 'paid'];
                /** @var mixed $payableStatus */
                $payableStatus = $payable->status;

                Log::info('Payable status: '.$payableStatus);
                Log::info('Terminal statuses: '.implode(', ', $terminalStatuses));
                if (\in_array($payableStatus, $terminalStatuses, true)) {
                    Log::info('Skipping payable already in terminal state', [
                        'transaction_id' => $transaction->id,
                        'payable_type' => \get_class($payable),
                        'payable_id' => $payable->id ?? null,
                        'payable_status' => $payableStatus,
                    ]);
                    $skippedCount++;
                    continue;
                }
            }

            try {
                // Call the onPaymentExpired method
                $payable->onPaymentExpired();

                $processedCount++;

                Log::info('Payment expiration handled', [
                    'transaction_id' => $transaction->id,
                    'transaction_reference' => $transaction->reference,
                    'payable_type' => get_class($payable),
                    'payable_id' => $payable->id,
                    'expired_at' => $transaction->expires_at->toDateTimeString(),
                    'payform_id' => $transaction->payform_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to handle payment expiration', [
                    'transaction_id' => $transaction->id,
                    'payable_type' => get_class($payable),
                    'payable_id' => $payable->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $skippedCount++;
            }
        }

        if ($processedCount > 0 || $skippedCount > 0) {
            Log::info('Expired payments processing completed', [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'total_expired_transactions' => $expiredTransactions->count(),
            ]);
        }
    }

    /**
     * Human-readable description
     */
    public function description(): string
    {
        return 'Process expired payment transactions and cancel associated payables';
    }

    /**
     * This task should run per-tenant
     */
    public function isTenantAware(): bool
    {
        return true;
    }

    /**
     * Unique identifier for this task
     */
    public function getIdentifier(): string
    {
        return 'payforms:cancel-expired-orders';
    }
}
