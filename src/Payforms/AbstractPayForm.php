<?php

namespace Ingenius\Payforms\Payforms;

use Ingenius\Core\Interfaces\HasFeature;
use Ingenius\Core\Interfaces\IWithPayment;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Ingenius\Core\Interfaces\FeatureInterface;
use JsonSerializable;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Exceptions\TransactionCreationException;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Models\PaymentTransactionStatus;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

abstract class AbstractPayForm implements Arrayable, Jsonable, JsonSerializable, HasFeature
{

    protected string $id = '';
    protected string $name = '';
    protected string $icon = '';
    protected string $description = '';
    protected bool $active = true;
    protected array $args = [];
    protected bool $sandbox = false;
    protected string $sandboxSuffix = '_sandbox';
    protected string $sandboxKey = 'use_sandbox';
    protected array $currencies = [];

    /**
     * Constructor for AbstractPayForm
     *
     * @throws \Exception When payform not found or validation fails
     */
    public function __construct()
    {
        $payform = PayFormData::where('payform_id', $this->getId())->first();

        if (!$payform) {
            $payform = PayFormData::create([
                'payform_id' => $this->getId(),
                'name' => $this->getName() ?? implode(' ', preg_split('/(?=[A-Z])/', class_basename(get_class($this)), -1, PREG_SPLIT_NO_EMPTY)),
                'description' => $this->getDescription() ?? '',
                'args' => $this->getDefaultArgs(),
                'icon' => $this->getIcon() ?? '',
                'active' => false,
            ]);
        }

        $this->name = $payform->name;
        $this->description = $payform->description;
        $this->args = $payform->args ?? [];
        $this->icon = $payform->icon ?? '';
        $this->active = $payform->active;
        $this->currencies = $payform->currencies ?? [];
    }

    public function configured(): bool
    {
        // Validate args
        $validator = Validator::make($this->args, $this->rules());

        return $validator->passes();
    }

    protected function getDefaultArgs(): array
    {
        return [];
    }

    /**
     * Get the ID of the payform
     *
     * @return int
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the label of the payform
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the icon of the payform
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get the description of the payform
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    /**
     * Define validation rules for payform args
     *
     * @return array Validation rules
     */
    public function rules(): array
    {
        return [];
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function getArg(string $key)
    {
        if ($this->sandbox && isset($this->args[$this->sandboxKey]) && $this->args[$this->sandboxKey]) {
            $key = $key . $this->sandboxSuffix;
        }


        return $this->args[$key] ?? null;
    }

    public function setArg(string $key, $value)
    {
        if ($this->sandbox && isset($this->args[$this->sandboxKey]) && $this->args[$this->sandboxKey]) {
            $key = $key . $this->sandboxSuffix;
        }

        $this->args[$key] = $value;

        $payform = PayFormData::where('payform_id', $this->getId())->first();
        $payform->args = $this->args;
        $payform->save();
    }

    /**
     * Get the number of hours until this payment form expires
     *
     * @return int Hours until expiration
     */
    public function getExpirationHours(): ?int
    {
        return 12; // Default expiration time: 12 hours
    }

    public function getCurrencies(): array
    {
        return $this->currencies;
    }

    /**
     * Create a transaction
     *
     * @param int $amount Amount to pay
     * @param array $metadata Additional metadata for the transaction
     * @param mixed $payable The model that is being paid for (optional)
     * @return PaymentResponse The payment response
     */
    public function createTransaction(int $amount, string $currency, array $metadata = [], $payable = null): PaymentResponse
    {
        try {
            // Create transaction in database
            $transaction = PaymentTransaction::createTransaction(
                $this->id,
                $amount,
                $currency,
                $metadata,
                $payable
            );

            // Set expiration date based on payment form's expiration hours
            $transaction->expires_at = $this->getExpirationHours() ? now()->addHours($this->getExpirationHours()) : null;
            $transaction->save();

            $result = $this->handleCreateTransaction($transaction, $payable);
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage());
            throw new TransactionCreationException('Error creating transaction: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Commit a payment
     *
     * @param PaymentTransaction $transaction The transaction to commit
     * @param array $data Additional data needed for payment
     * @return mixed Result of the payment
     */
    public function commitPayment(array $data = [])
    {
        $result = $this->handleCommitPayment($data);

        if ($result && $result->status == PaymentStatus::APPROVED) {
            $paymentTransaction = $result->transaction;
            $payable = $paymentTransaction->payable;

            if ($payable && $payable instanceof IWithPayment) {

                $paidOrderStatusClass = config('payforms.paid_order_status_class');
                $paidOrderStatus = new $paidOrderStatusClass();
                $payable->onPaymentSuccess($paidOrderStatus->getIdentifier());
            }
        }

        return $result;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'description' => $this->description,
            'currencies' => $this->currencies,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the required feature identifier for this payform.
     *
     * @return string
     */
    abstract public function getRequiredFeature(): FeatureInterface;

    /**
     * Handle the creation of a transaction.
     *
     * @param PaymentTransaction $transaction
     * @param mixed $payable
     * @return PaymentResponse
     */
    abstract protected function handleCreateTransaction(PaymentTransaction $transaction, $payable = null): PaymentResponse;

    /**
     * Handle the commitment of a payment.
     *
     * @param array $data
     * @return PaymentTransactionStatus|null
     */
    abstract protected function handleCommitPayment(array $data): PaymentTransactionStatus|null;
}
