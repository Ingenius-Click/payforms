<?php

namespace Ingenius\Payforms\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Ingenius\Payforms\Constants\PayformPermissions;

class PayFormDataPolicy
{
    public function update($user): bool
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(PayformPermissions::UPDATE_PAYFORM);
        }

        return false;
    }
}
