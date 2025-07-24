<?php

namespace Ingenius\Payforms\InvoiceData;

use Ingenius\Orders\Data\InvoiceDataSection;
use Ingenius\Orders\Interfaces\InvoiceDataProviderInterface;
use Ingenius\Orders\Models\Invoice;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Services\PayformsManager;

class PayformInvoiceDataProvider implements InvoiceDataProviderInterface
{
    /**
     * @var PayformsManager
     */
    protected PayformsManager $payformsManager;

    /**
     * Create a new payform invoice data provider.
     *
     * @param PayformsManager $payformsManager
     */
    public function __construct(PayformsManager $payformsManager)
    {
        $this->payformsManager = $payformsManager;
    }

    /**
     * Get the invoice data sections.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function getInvoiceData(Invoice $invoice): array
    {
        $orderable = $invoice->orderable;

        if (!$orderable) {
            return [];
        }

        // Find the payment transaction for this orderable
        $transaction = PaymentTransaction::where('payable_id', $orderable->id)
            ->where('payable_type', get_class($orderable))
            ->first();

        if (!$transaction) {
            return [];
        }

        // Get the payform information
        try {
            $payform = $this->payformsManager->getPayform($transaction->payform_id);

            $properties = [
                'Payment Method' => $payform->getName(),
                'Transaction Reference' => $transaction->reference,
            ];

            if ($transaction->external_id) {
                $properties['External Reference'] = $transaction->external_id;
            }

            // Create a payment information section
            $section = new InvoiceDataSection('Payment Information', $properties, 20);

            return [$section];
        } catch (\Exception $e) {
            // If the payform is not found, return an empty array
            return [];
        }
    }

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'PayformInvoiceDataProvider';
    }

    /**
     * Get the provider priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 20;
    }
}
