<?php

namespace Ingenius\Payforms\Exceptions;

/**
 * The gateway never created a payment: the request was rejected before any
 * state was established (validation error, bad credentials, unreachable host,
 * misconfigured payform).
 *
 * Nothing exists remotely, so the caller is free to undo its local work.
 */
class PaymentNotStartedException extends PaymentGatewayException
{
    public function isSafeToCompensate(): bool
    {
        return true;
    }
}
