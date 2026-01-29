<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class EnzonaPayformFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'enzona-payform';
    }

    public function getName(): string
    {
        return __('Enzona payform');
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
        return false;
    }
}