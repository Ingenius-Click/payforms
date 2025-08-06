<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class CashPayformFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'cash-payform';
    }

    public function getName(): string
    {
        return __('Cash payform');
    }

    public function getGroup(): string
    {
        return __('Payforms');
    }

    public function getPackage(): string
    {
        return 'payforms';
    }

    public function isBasic(): bool
    {
        return true;
    }
}
