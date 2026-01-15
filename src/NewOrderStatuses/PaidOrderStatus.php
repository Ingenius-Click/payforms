<?php

namespace Ingenius\Payforms\NewOrderStatuses;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ingenius\Core\Interfaces\IInventoriable;
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
        return __('Paid');
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
        // Create invoice
        $invoiceService = app(InvoiceCreationManager::class);
        $invoiceService->attemptToCreateInvoice($order, now()->toDateTimeString(), $this->getIdentifier());

        // Deduct inventory for paid orders
        $this->deductInventory($order);
    }

    /**
     * Deduct inventory for all products in the order.
     * Uses database transactions to ensure atomic operations.
     *
     * @param Order $order
     * @return void
     */
    protected function deductInventory(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->products as $orderProduct) {
                // Get the actual product model (Product, etc.)
                $product = $orderProduct->productible;

                // Skip if product doesn't exist or doesn't implement IInventoriable
                if (!$product || !($product instanceof IInventoriable)) {
                    continue;
                }

                // Only deduct stock if product handles stock management
                if (!$product->handleStock()) {
                    continue;
                }

                // Validate sufficient stock before deduction
                if (!$product->hasEnoughStock($orderProduct->quantity)) {
                    Log::warning('Insufficient stock for order product during payment', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'product_id' => $product->id,
                        'product_name' => $product->name ?? 'Unknown',
                        'quantity_ordered' => $orderProduct->quantity,
                        'stock_available' => $product->getStock(),
                    ]);

                    // Continue with deduction but log the warning
                    // In production, you might want to throw an exception here
                    // or send an alert to admins
                }

                // Deduct the stock
                $stockBefore = $product->getStock();
                $product->removeStock($orderProduct->quantity);
                $stockAfter = $product->getStock();

                // Log inventory deduction for audit trail
                Log::info('Inventory deducted for paid order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_id' => $product->id,
                    'product_name' => $product->name ?? 'Unknown',
                    'product_type' => get_class($product),
                    'quantity_deducted' => $orderProduct->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'previous_status' => $previousStatusIdentifier ?? 'unknown',
                ]);
            }
        });
    }
}
