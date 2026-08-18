<?php

namespace Ingenius\Payforms\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ingenius\Core\Interfaces\IOrderable;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Exceptions\PaymentStatusConflictException;

class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payform_id',
        'reference',
        'idempotency_key',
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
        // Ordered by id, not created_at: two statuses recorded within the same
        // second must still resolve to the one written last.
        $latestStatus = $this->statuses()->latest('id')->first();
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
     * Get the idempotency key for this transaction, generating one if the
     * transaction predates the column.
     *
     * The key is stable for the lifetime of the transaction so that retrying a
     * gateway request that may already have been processed resolves to the
     * same remote payment instead of creating a second one.
     */
    public function getIdempotencyKey(): string
    {
        if (!$this->idempotency_key) {
            $this->idempotency_key = (string) Str::uuid();
            $this->save();
        }

        return $this->idempotency_key;
    }

    /**
     * Create a new transaction instance.
     */
    public static function createTransaction(string $payform_id, int $amount, string $currency, array $metadata = [], $payable = null): self
    {
        $transaction = new self([
            'payform_id' => $payform_id,
            'reference' => $payable ? ($payable instanceof IOrderable ? $payable->getOrderableCode() : self::generateReference()) : self::generateReference(),
            'idempotency_key' => (string) Str::uuid(),
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

    /**
     * Mark this transaction as approved.
     *
     * Idempotent: an already approved transaction returns its existing status
     * record untouched, so a redelivered gateway callback is a no-op rather
     * than an error.
     *
     * @throws PaymentStatusConflictException If the transaction already reached a different final status
     */
    public function pay(): PaymentTransactionStatus
    {
        return $this->transitionTo(PaymentStatus::APPROVED);
    }

    /**
     * Mark this transaction as rejected.
     *
     * Idempotent under the same rules as pay().
     *
     * @throws PaymentStatusConflictException If the transaction already reached a different final status
     */
    public function reject(): PaymentTransactionStatus
    {
        return $this->transitionTo(PaymentStatus::REJECTED);
    }

    /**
     * Move a pending transaction to a final status.
     *
     * The row is locked for the duration of the check so that two callbacks
     * arriving at once cannot both observe PENDING and both record a status.
     * Use PaymentTransactionStatus::isNewTransition() on the result to tell an
     * actual state change from a replay.
     *
     * @throws PaymentStatusConflictException If the transaction is in a final status other than the target
     */
    protected function transitionTo(PaymentStatus $status): PaymentTransactionStatus
    {
        return DB::transaction(function () use ($status) {
            static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            $currentStatus = $this->getCurrentStatus();

            if ($currentStatus === $status) {
                // Already in the target state — hand back the existing record so
                // wasRecentlyCreated stays false and callers skip their side effects.
                return $this->statuses()->latest('id')->first();
            }

            if ($currentStatus !== PaymentStatus::PENDING) {
                throw new PaymentStatusConflictException($currentStatus, $status);
            }

            return $this->setStatus($status);
        });
    }
}
