<?php

namespace Ingenius\Payforms\Payforms\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Ingenius\Payforms\Models\PaymentTransaction;

class PaymentResponse implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Response types
     */
    public const TYPE_NONE = 'none';
    public const TYPE_REDIRECT = 'redirect';
    public const TYPE_QR = 'qr';
    public const TYPE_FORM = 'form';
    public const TYPE_COMPONENT = 'component';
    public const TYPE_INFO = 'info';

    /**
     * @var PaymentTransaction
     */
    protected PaymentTransaction $transaction;

    /**
     * @var string
     */
    protected string $type;

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Create a new payment response.
     *
     * @param PaymentTransaction $transaction
     * @param string $type
     * @param array $data
     * @param string|null $message
     */
    public function __construct(PaymentTransaction $transaction, string $type = self::TYPE_NONE, array $data = [], ?string $message = null)
    {
        $this->transaction = $transaction;
        $this->type = $type;
        $this->data = $data;
        $this->message = $message;
    }

    /**
     * Create a simple response with no additional action required.
     *
     * @param PaymentTransaction $transaction
     * @param string|null $message
     * @return static
     */
    public static function none(PaymentTransaction $transaction, ?string $message = null): self
    {
        return new static($transaction, self::TYPE_NONE, [], $message);
    }

    /**
     * Create a redirect response.
     *
     * @param PaymentTransaction $transaction
     * @param string $url
     * @param string|null $message
     * @return static
     */
    public static function redirect(PaymentTransaction $transaction, string $url, ?string $message = null): self
    {
        return new static($transaction, self::TYPE_REDIRECT, ['url' => $url], $message);
    }

    /**
     * Create a QR code response.
     *
     * @param PaymentTransaction $transaction
     * @param string $qrContent
     * @param string|null $message
     * @return static
     */
    public static function qr(PaymentTransaction $transaction, string $qrContent, ?string $message = null): self
    {
        return new static($transaction, self::TYPE_QR, ['content' => $qrContent], $message);
    }

    /**
     * Create a form response.
     *
     * @param PaymentTransaction $transaction
     * @param array $formFields
     * @param string|null $message
     * @return static
     */
    public static function form(PaymentTransaction $transaction, array $formFields, ?string $message = null): self
    {
        return new static($transaction, self::TYPE_FORM, ['fields' => $formFields], $message);
    }

    /**
     * Create a component response.
     *
     * @param PaymentTransaction $transaction
     * @param string $component
     * @param array $props
     * @param string|null $message
     * @return static
     */
    public static function component(PaymentTransaction $transaction, string $component, array $props = [], ?string $message = null): self
    {
        return new static($transaction, self::TYPE_COMPONENT, [
            'component' => $component,
            'props' => $props
        ], $message);
    }

    /**
     * Create an info response with instructions.
     *
     * @param PaymentTransaction $transaction
     * @param string $instructions
     * @param string $email
     * @param string|null $message
     * @return static
     */
    public static function info(PaymentTransaction $transaction, string $instructions, string $email, ?string $message = null): self
    {
        return new static($transaction, self::TYPE_INFO, [
            'instructions' => $instructions,
            'email' => $email
        ], $message);
    }

    /**
     * Get the transaction.
     *
     * @return PaymentTransaction
     */
    public function getTransaction(): PaymentTransaction
    {
        return $this->transaction;
    }

    /**
     * Get the response type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the response data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the response message.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'reference' => $this->transaction->reference,
                'amount' => $this->transaction->amount,
                'currency' => $this->transaction->currency,
                'status' => $this->transaction->getCurrentStatus()->value,
            ],
            'type' => $this->type,
            'data' => $this->data,
            'message' => $this->message,
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
}
