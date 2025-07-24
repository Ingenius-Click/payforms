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
        return 'Manual status change';
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
