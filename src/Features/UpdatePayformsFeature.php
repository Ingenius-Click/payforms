<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class UpdatePayformsFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'update-payforms';
    }

    public function getName(): string
    {
        return __('Update payforms');
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
