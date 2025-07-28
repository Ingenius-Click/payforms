<?php

namespace Ingenius\Payforms\Policies;

use Ingenius\Payforms\Constants\PaymentTransitionPermissions;

class PaymentTransactionPolicy
{
    public function changeStatus($user): bool
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(PaymentTransitionPermissions::MANUAL_STATUS_CHANGE);
        }

        return false;
    }
}
