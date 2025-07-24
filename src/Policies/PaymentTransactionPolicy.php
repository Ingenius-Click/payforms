<?php

namespace Ingenius\Payforms\Policies;

use Ingenius\Auth\Models\User;
use Ingenius\Payforms\Constants\PaymentTransitionPermissions;

class PaymentTransactionPolicy
{
    public function changeStatus(User $user): bool
    {
        return $user->can(PaymentTransitionPermissions::MANUAL_STATUS_CHANGE);
    }
}
