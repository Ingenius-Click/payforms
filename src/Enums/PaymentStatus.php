<?php

namespace Ingenius\Payforms\Enums;

enum PaymentStatus: string
{
/**
     * The transaction has started but is not yet completed.
     * The user may be entering their details, or the system is waiting for bank confirmation.
     */
    case PENDING = 'pending';

/**
     * The payment was successfully authorized by the financial institution.
     * The funds have been debited from the customer and are being settled or already transferred to the merchant.
     */
    case APPROVED = 'approved';

/**
     * The payment attempt was declined by the bank or failed due to a technical error,
     * insufficient funds, invalid card, etc.
     */
    case REJECTED = 'rejected';

/**
     * The payment is under manual or automatic review (e.g., due to suspected fraud).
     */
    case IN_PROCESS = 'in_process';

/**
     * The transaction is paused for some reason, such as waiting for customer
     * or merchant action (e.g., ID verification or receipt confirmation).
     */
    case ON_HOLD = 'on_hold';

/**
     * The money has been returned to the customer after a cancellation, dispute, or return.
     */
    case REFUNDED = 'refunded';

/**
     * The transaction was voided before being completed, either by the customer,
     * the system, or the merchant.
     */
    case CANCELED = 'canceled';

    /**
     * The payment was registered manually without going through a payment gateway.
     * Used for manual invoices where payment is confirmed outside the system.
     */
    case MANUAL = 'manual';

    /**
     * Get the display name for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Initiated / Pending',
            self::APPROVED => 'Approved / Successful',
            self::REJECTED => 'Rejected / Failed',
            self::IN_PROCESS => 'In Process / Under Review',
            self::ON_HOLD => 'On Hold / Suspended',
            self::REFUNDED => 'Refunded / Returned',
            self::CANCELED => 'Canceled',
            self::MANUAL => 'Manual Payment',
        };
    }

    /**
     * Get the icon/symbol for the status
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => '⏳',
            self::APPROVED => '✅',
            self::REJECTED => '❌',
            self::IN_PROCESS => '🔄',
            self::ON_HOLD => '🕓',
            self::REFUNDED => '↩️',
            self::CANCELED => '🛑',
            self::MANUAL => '📝',
        };
    }
}
