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
}
