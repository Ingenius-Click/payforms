<?php

namespace Ingenius\Payforms\Listeners;

use Illuminate\Support\Facades\Log;
use Ingenius\Orders\Events\OrderStatusChangedEvent;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Models\PaymentTransaction;

/**
 * Sync payment transaction status when order status changes
 *
 * This listener maintains data consistency between orders and their
 * payment transactions by updating transaction status based on order status.
 */
class SyncTransactionStatusOnOrderChange
{
    /**
     * Handle the event.
     */
    public function handle(OrderStatusChangedEvent $event): void
    {
        $order = $event->getOrder();
        $newStatus = $order->status;

        // Find the payment transaction for this order
        $transaction = PaymentTransaction::where('payable_type', get_class($order))
            ->where('payable_id', $order->id)
            ->first();

        if (!$transaction) {
            // No transaction found - order might not have been created through payment flow
            return;
        }

        $currentTransactionStatus = $transaction->getCurrentStatus();

        // Only update if transaction is still PENDING
        if ($currentTransactionStatus !== PaymentStatus::PENDING) {
            // Transaction already has a final status, don't override
            return;
        }

        // Map order status to transaction status
        $transactionStatus = $this->mapOrderStatusToTransactionStatus($newStatus);

        if ($transactionStatus) {
            $transaction->setStatus($transactionStatus);

            Log::info('Payment transaction status synced from order status change', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $newStatus,
                'previous_order_status' => $event->getPreviousStatus(),
                'transaction_id' => $transaction->id,
                'transaction_status' => $transactionStatus->value,
            ]);
        }
    }

    /**
     * Map order status to payment transaction status
     *
     * @param string $orderStatus
     * @return PaymentStatus|null
     */
    protected function mapOrderStatusToTransactionStatus(string $orderStatus): ?PaymentStatus
    {
        return match ($orderStatus) {
            'paid', 'completed' => PaymentStatus::APPROVED,
            'cancelled' => PaymentStatus::CANCELED,
            default => null, // Don't update for other statuses
        };
    }
}
