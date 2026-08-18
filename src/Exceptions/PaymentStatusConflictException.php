<?php

namespace Ingenius\Payforms\Exceptions;

use Exception;
use Ingenius\Payforms\Enums\PaymentStatus;

/**
 * Thrown when a transaction is asked to move to a status it cannot reach from
 * its current one (e.g. rejecting an already approved payment).
 *
 * This is a permanent condition: retrying the same request will never succeed,
 * so callers handling gateway callbacks should acknowledge instead of asking
 * the gateway to redeliver.
 */
class PaymentStatusConflictException extends Exception
{
    public function __construct(
        public readonly ?PaymentStatus $currentStatus,
        public readonly PaymentStatus $attemptedStatus,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Cannot transition payment transaction from %s to %s.',
            $currentStatus?->value ?? 'no status',
            $attemptedStatus->value,
        ));
    }
}
