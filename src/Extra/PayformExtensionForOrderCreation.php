<?php

namespace Ingenius\Payforms\Extra;

use Illuminate\Http\Request;
use Ingenius\Orders\Extensions\BaseOrderExtension;
use Ingenius\Orders\Models\Order;
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

        // Use the subtotal from the context (which includes all previous extensions)
        $amount = $context['subtotal'];

        // Create payment transaction
        $transaction = $payform->createTransaction($amount, $order->getCurrency(), [], $order);

        // Return payment data to be sent to the client
        return $transaction->toArray();
    }

    public function calculateSubtotal(Order $order, float $currentSubtotal, array &$context): float
    {
        // Payment processing doesn't modify the subtotal
        return $currentSubtotal;
    }

    public function extendOrderArray(Order $order, array $orderArray): array
    {
        // Add payment information to the order array if needed
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
