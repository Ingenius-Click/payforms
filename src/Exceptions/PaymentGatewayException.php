<?php

namespace Ingenius\Payforms\Exceptions;

use Exception;

/**
 * Base class for failures that occur while asking a gateway to start a payment.
 *
 * Subclasses answer the only question the caller actually needs: is it safe to
 * undo the local work, or might a payment already exist on the gateway side?
 */
abstract class PaymentGatewayException extends Exception
{
    /**
     * Whether the gateway provably did not create a payment.
     *
     * True means the caller may safely compensate (cancel the order, restore
     * the cart). False means the outcome is unknown and the local records must
     * stand until something reconciles them against the gateway.
     */
    abstract public function isSafeToCompensate(): bool;
}
