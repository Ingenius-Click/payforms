<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class ManualStatusChangeFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'manual-status-change';
    }

    public function getName(): string
    {
        return __('Manual status change');
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
