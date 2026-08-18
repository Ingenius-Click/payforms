<?php

namespace Ingenius\Payforms\Exceptions;

/**
 * The request reached the gateway — or may have — but no usable answer came
 * back: a timeout, a dropped connection, a 5xx.
 *
 * A payment may well exist remotely and the customer may still pay it, so the
 * local transaction must be left pending rather than cancelled. Resolving it
 * requires querying the gateway, not guessing.
 */
class PaymentOutcomeUnknownException extends PaymentGatewayException
{
    public function isSafeToCompensate(): bool
    {
        return false;
    }
}
