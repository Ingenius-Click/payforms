<?php

namespace Ingenius\Payforms\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Ingenius\Core\Interfaces\IOrderable;
use Ingenius\Payforms\Enums\PaymentStatus;

class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payform_id',
        'reference',
        'external_id',
        'amount',
        'currency',
        'metadata',
        'payable_type',
        'payable_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the payform that this transaction belongs to.
     */
    public function payform(): BelongsTo
    {
        return $this->belongsTo(PayFormData::class, 'payform_id', 'payform_id');
    }

    /**
     * Get the payable entity (polymorphic).
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all statuses for this transaction.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(PaymentTransactionStatus::class, 'payment_transaction_id');
    }

    /**
     * Get the current status of the transaction.
     *
     * @return PaymentStatus|null
     */
    public function getCurrentStatus(): ?PaymentStatus
    {
        $latestStatus = $this->statuses()->latest('created_at')->first();
        return $latestStatus ? $latestStatus->status : null;
    }

    /**
     * Set a new status for this transaction.
     *
     * @param PaymentStatus $status
     * @return PaymentTransactionStatus
     */
    public function setStatus(PaymentStatus $status): PaymentTransactionStatus
    {
        return $this->statuses()->create([
            'status' => $status,
        ]);
    }

    /**
     * Generate a unique reference for this transaction.
     */
    public static function generateReference(): string
    {
        return strtoupper(uniqid('PAY-'));
    }

    /**
     * Create a new transaction instance.
     */
    public static function createTransaction(string $payform_id, int $amount, string $currency, array $metadata = [], $payable = null): self
    {
        $transaction = new self([
            'payform_id' => $payform_id,
            'reference' => $payable ? ($payable instanceof IOrderable ? $payable->getOrderableCode() : self::generateReference()) : self::generateReference(),
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);

        if ($payable) {
            $transaction->payable()->associate($payable);
        }

        $transaction->save();

        // Set initial status
        $transaction->setStatus(PaymentStatus::PENDING);

        return $transaction;
    }

    /**
     * Scope a query to only include expired payment transactions.
     *
     * Finds transactions where:
     * 1. expires_at is in the past
     * 2. The latest status is PENDING
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('expires_at', '<', Carbon::now())
            ->whereNotNull('expires_at')
            ->whereHas('statuses', function ($statusQuery) {
                // Get the latest status for each transaction
                $statusQuery->whereIn('id', function ($subQuery) {
                    $subQuery->selectRaw('MAX(id)')
                        ->from('payment_transaction_statuses')
                        ->whereColumn('payment_transaction_id', 'payment_transactions.id')
                        ->groupBy('payment_transaction_id');
                })
                ->where('status', PaymentStatus::PENDING);
            });
    }

    public function pay(): PaymentTransactionStatus {

        $currentStatus = $this->getCurrentStatus();

        if($currentStatus != PaymentStatus::PENDING) {
            throw new \Exception("Only PENDING transactions can be paid.");
        }

        return $this->setStatus(PaymentStatus::APPROVED);
    }
}
