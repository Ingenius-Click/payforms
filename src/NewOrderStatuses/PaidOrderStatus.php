<?php

namespace Ingenius\Payforms\NewOrderStatuses;

use Ingenius\Orders\Interfaces\OrderStatusInterface;
use Ingenius\Orders\Models\Order;
use Ingenius\Orders\Services\InvoiceCreationManager;

class PaidOrderStatus implements OrderStatusInterface
{

    public function getIdentifier(): string
    {
        return 'paid';
    }

    public function getName(): string
    {
        return 'Paid';
    }

    public function getDescription(): string
    {
        return 'The order has been paid successfully.';
    }

    public function canTransitionTo(string $targetStatusIdentifier, Order $order): bool
    {
        // Allow transition to completed status
        return $targetStatusIdentifier === 'completed';
    }

    public function onExit(Order $order, string $targetStatusIdentifier): void
    {
        /**
         * This method is intentionally left empty as no special actions are needed
         * when transitioning from the 'paid' status to another status.
         * The payment has already been processed successfully, so no cleanup or
         * additional operations are required when exiting this status.
         */
    }

    public function onEnter(Order $order, string $previousStatusIdentifier): void
    {
        /**
         * This method is intentionally left empty as all payment processing logic
         * is handled before the order reaches this status. By the time an order
         * enters the 'paid' status, the payment has been verified and recorded.
         * No additional actions are needed when entering this state.
         */
        $invoiceService = app(InvoiceCreationManager::class);
        $invoiceService->attemptToCreateInvoice($order, now()->toDateTimeString(), $this->getIdentifier());
    }
}
