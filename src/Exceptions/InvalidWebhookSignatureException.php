<?php

namespace Ingenius\Payforms\Exceptions;

use Exception;

/**
 * Thrown when a gateway callback fails signature verification.
 *
 * Like a status conflict this is permanent — redelivering the same payload
 * produces the same result — so it must not be answered with a retryable
 * server error.
 */
class InvalidWebhookSignatureException extends Exception
{
    // Custom exception for callbacks that fail signature verification
}
