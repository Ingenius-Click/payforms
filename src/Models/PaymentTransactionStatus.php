<?php

namespace Ingenius\Payforms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ingenius\Payforms\Enums\PaymentStatus;

class PaymentTransactionStatus extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'status' => PaymentStatus::class,
    ];


    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /**
     * Whether this record represents a status the transaction just moved to,
     * as opposed to one it already held.
     *
     * Callers use this to run side effects (transitioning the payable, sending
     * notifications) exactly once even when a gateway redelivers a callback.
     */
    public function isNewTransition(): bool
    {
        return $this->wasRecentlyCreated;
    }
}
