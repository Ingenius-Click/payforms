<?php

namespace Ingenius\Payforms\Extra;

use Illuminate\Http\Request;
use Ingenius\Orders\Extensions\BaseOrderExtension;
use Ingenius\Orders\Models\Order;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Services\PayformsManager;

class PayformExtensionForOrderCreation extends BaseOrderExtension
{
    public function __construct(
        protected PayformsManager $payformsManager
    ) {}

    public function getValidationRules(Request $request): array
    {
        $actives = array_map(function ($payForm) {
            return $payForm->getId();
        }, $this->payformsManager->getActivePayforms());

        return [
            'payform_id' => 'required|in:' . implode(',', $actives),
        ];
    }

    public function processOrder(Order $order, array $validatedData, array &$context): array
    {
        $payform = $this->payformsManager->getPayform($validatedData['payform_id']);

        if (!$payform) {
            return [];
        }

        // Use the total from the context (which includes all previous extensions: discounts, shipping, etc.)
        $amount = $context['total'];

        $metadata = [
            'customer' => [
                'name' => $order->customer_name ?? '',
                'email' => $order->customer_email ?? '',
                'phone' => $order->customer_phone ?? '',
                'address' => $order->customer_address ?? ''
            ]
        ];

        // Check if this is a manual invoice - bypass payment flow
        // Note: is_manual_invoice is set internally by CreateOrderAction, not from request data
        if (!empty($context['is_manual_invoice'])) {
            return $this->createManualTransaction($payform, $amount, $order, $metadata);
        }

        // Create payment transaction (triggers normal payment flow)
        $transaction = $payform->createTransaction($amount, $order->getCurrency(), $metadata, $order);

        // Return payment data to be sent to the client
        return $transaction->toArray();
    }

    /**
     * Create a payment transaction for manual invoices without triggering payment flow.
     * The transaction is created directly with MANUAL status.
     *
     * @param mixed $payform The payform instance
     * @param int $amount Amount in cents
     * @param Order $order The order
     * @param array $metadata Transaction metadata
     * @return array Transaction data
     */
    protected function createManualTransaction($payform, int $amount, Order $order, array $metadata): array
    {
        // Create transaction record directly without triggering payment gateway
        $transaction = PaymentTransaction::createTransaction(
            $payform->getId(),
            $amount,
            $order->getCurrency(),
            $metadata,
            $order
        );

        // Override the PENDING status with MANUAL (payment confirmed outside the system)
        $transaction->setStatus(PaymentStatus::MANUAL);

        return [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => PaymentStatus::MANUAL->value,
            'is_manual' => true,
        ];
    }

    public function calculateSubtotal(Order $order, float $currentSubtotal, array &$context): float
    {
        // Payment processing doesn't modify the subtotal
        return $currentSubtotal;
    }

    public function extendOrderArray(Order $order, array $orderArray): array
    {
        // Add payment information to the order array if needed
        $orderClass = get_class($order);

        $payment = PaymentTransaction::where('payable_id', $order->id)->where('payable_type', $orderClass)->first();

        $orderArray['payform'] = [
            'reference' => $payment->reference,
            'external_id' => $payment->external_id,
            'amount' => $payment->amount,
            'amount_converted' => $payment->amount * $order->exchange_rate,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'expires_at' => $payment->expires_at,
            'metadata' => $payment->metadata,
            'payform_id' => $payment->payform_id,
            'payform_name' => $payment->payform->name,
            'payform_logo' => $payment->payform->icon,
        ];

        return $orderArray;
    }

    public function getPriority(): int
    {
        // Run last to ensure all price modifications are included
        return 100;
    }

    public function getName(): string
    {
        return 'PaymentProcessor';
    }
}
