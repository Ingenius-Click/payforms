<?php

namespace Ingenius\Payforms\Exceptions;

use Exception;

/**
 * Thrown when a gateway callback cannot be processed and never will be —
 * an unknown reference, a payload missing the fields needed to identify a
 * transaction, and similar.
 *
 * Redelivering the same callback produces the same outcome, so it must be
 * acknowledged rather than answered with a retryable server error. It still
 * warrants an error-level log: an unknown reference usually means a payment
 * exists at the gateway with no local transaction behind it.
 */
class UnrecoverableCallbackException extends Exception
{
    // Custom exception for callbacks that can never be processed successfully
}
