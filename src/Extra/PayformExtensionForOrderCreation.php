<?php

namespace Ingenius\Payforms\Extra;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ingenius\Orders\Exceptions\OrderFinalizationFailedException;
use Ingenius\Orders\Extensions\BaseOrderExtension;
use Ingenius\Orders\Interfaces\DeferredOrderExtensionInterface;
use Ingenius\Orders\Models\Order;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Exceptions\PaymentGatewayException;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Services\PayformsManager;

class PayformExtensionForOrderCreation extends BaseOrderExtension implements DeferredOrderExtensionInterface
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

    /**
     * Database-only phase, running inside the order transaction.
     *
     * Records the pending payment transaction but does not contact the gateway:
     * an external call here would hold the transaction (and its row locks) open
     * for as long as the gateway takes to answer. The call itself happens in
     * finalizeOrder(), once the order is committed.
     */
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

        $transaction = PaymentTransaction::createTransaction(
            $payform->getId(),
            $amount,
            $order->getCurrency(),
            $metadata,
            $order
        );

        $transaction->expires_at = $payform->getExpirationHours()
            ? now()->addHours($payform->getExpirationHours())
            : null;
        $transaction->save();

        // Handed to finalizeOrder() so it does not have to look the row up again.
        $context['payment_transaction_id'] = $transaction->id;
        $context['payform_id'] = $payform->getId();

        return [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => PaymentStatus::PENDING->value,
        ];
    }

    /**
     * External phase, running after the order has been committed.
     *
     * Asks the gateway to start the payment and returns whatever the customer
     * needs to complete it (a QR, a redirect URL, a form).
     */
    public function finalizeOrder(Order $order, array $validatedData, array $context): array
    {
        // Manual invoices never reach a gateway.
        if (!empty($context['is_manual_invoice']) || empty($context['payment_transaction_id'])) {
            return [];
        }

        $payform = $this->payformsManager->getPayform($context['payform_id'] ?? '');
        $transaction = PaymentTransaction::find($context['payment_transaction_id']);

        if (!$payform || !$transaction) {
            return [];
        }

        try {
            return $payform->startPayment($transaction, $order)->toArray();
        } catch (PaymentGatewayException $e) {
            if ($e->isSafeToCompensate()) {
                // Nothing exists at the gateway: close the transaction out and
                // tell the caller the order should not stand.
                $transaction->setStatus(PaymentStatus::CANCELED);

                throw new OrderFinalizationFailedException(
                    $e->getMessage(),
                    $this->getName(),
                    $e,
                );
            }

            // The payment may exist remotely. Leave the transaction PENDING so
            // reconciliation (or the customer paying anyway) can settle it, and
            // let the order stand.
            Log::warning('Payment outcome unknown, leaving order pending', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'error' => $e->getMessage(),
            ]);

            return $this->unknownOutcomeResult($transaction);
        } catch (\Exception $e) {
            // Unclassified failure: it is not known whether the gateway was
            // reached, so the same conservative rule applies — never cancel an
            // order whose payment might exist.
            Log::error('Unexpected error starting payment, leaving order pending', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'error' => $e->getMessage(),
            ]);

            return $this->unknownOutcomeResult($transaction);
        }
    }

    /**
     * Payload telling the client its order stands but the payment could not be
     * confirmed, so it should poll rather than expect payment instructions.
     *
     * @param PaymentTransaction $transaction
     * @return array
     */
    protected function unknownOutcomeResult(PaymentTransaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'status' => PaymentStatus::PENDING->value,
            'outcome_unknown' => true,
            'message' => __('We could not confirm the payment status. Your order is being verified.'),
        ];
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

        $payment = PaymentTransaction::where('payable_id', $order->id)
            ->where('payable_type', $orderClass)
            ->latest('id')
            ->first();

        // Orders created without a payform have no transaction to describe.
        if (!$payment) {
            $orderArray['payform'] = null;

            return $orderArray;
        }

        $orderArray['payform'] = [
            'reference' => $payment->reference,
            'external_id' => $payment->external_id,
            'amount' => $payment->amount,
            'amount_converted' => $payment->amount * $order->exchange_rate,
            'currency' => $payment->currency,
            // Read from the status history: payment_transactions has no status column.
            'status' => $payment->getCurrentStatus()?->value,
            'expires_at' => $payment->expires_at,
            'metadata' => $payment->metadata,
            'payform_id' => $payment->payform_id,
            'payform_name' => $payment->payform?->name,
            'payform_logo' => $payment->payform?->icon,
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
