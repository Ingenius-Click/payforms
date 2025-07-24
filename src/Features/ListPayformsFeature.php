<?php

namespace Ingenius\Payforms\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class ListPayformsFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'list-payforms';
    }

    public function getName(): string
    {
        return 'List payforms';
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
