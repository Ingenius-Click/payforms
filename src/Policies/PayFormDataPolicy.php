<?php

namespace Ingenius\Payforms\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Ingenius\Auth\Models\User;
use Ingenius\Payforms\Constants\PayformPermissions;

class PayFormDataPolicy
{
    public function update(User $user): bool
    {
        return $user->can(PayformPermissions::UPDATE_PAYFORM);
    }
}
