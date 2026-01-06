<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class TransfermovilPayformFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'transfermovil-payform';
    }

    public function getName(): string
    {
        return __('Transfermovil payform');
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