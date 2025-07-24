<?php

namespace Ingenius\Payforms\Services;

use Ingenius\Coins\Services\CurrencyServices;
use Ingenius\Payforms\Exceptions\PayformAlreadyRegisteredException;
use Ingenius\Payforms\Exceptions\PayformNotFoundException;
use Ingenius\Payforms\Exceptions\PayformNotActiveException;
use Ingenius\Payforms\Payforms\AbstractPayForm;

class PayformsManager
{
    protected $payforms = [];

    public function registerPayform(string $payform_id, string $payform_class)
    {
        if (isset($this->payforms[$payform_id])) {
            throw new PayformAlreadyRegisteredException("Payform already registered: {$payform_id}");
        }

        $this->payforms[$payform_id] = $payform_class;
    }

    public function getPayform($payform_id): AbstractPayForm
    {
        $payform = $this->payforms[$payform_id] ?? null;

        if (!$payform) {
            throw new PayformNotFoundException("Payform not found: {$payform_id}");
        }

        $payform = new $payform();

        if (!$payform->getActive()) {
            throw new PayformNotActiveException("Payform is not active: {$payform_id}");
        }

        return $payform;
    }

    /**
     * Get payform IDs that the tenant has access to based on features
     *
     * @return array Array of payform IDs
     */
    public function getTenantAccessiblePayformIds(): array
    {
        if (!tenant()) {
            return [];
        }

        $accessibleIds = [];

        foreach ($this->payforms as $payformId => $payformClass) {
            $payformInstance = new $payformClass();
            $requiredFeature = $payformInstance->getRequiredFeature();

            if (tenant()->hasFeature($requiredFeature)) {
                $accessibleIds[] = $payformId;
            }
        }

        return $accessibleIds;
    }

    /**
     * Get all active payment forms
     *
     * @param string|null $currency Optional currency shortname to filter payment forms by
     * @return array Active payment forms that support the specified currency
     */
    public function getActivePayforms(?string $currency = null)
    {
        $instances = array_map(function ($payform) {
            return new $payform();
        }, array_values($this->payforms));

        // Filter by feature access first
        $featureAccessiblePayforms = array_filter($instances, function ($payform) {
            return tenant() && tenant()->hasFeature($payform->getRequiredFeature()->getIdentifier());
        });

        // Filter by active status
        $activePayforms = array_filter($featureAccessiblePayforms, function ($payform) {
            return $payform->getActive();
        });

        $configuredPayforms = array_filter($activePayforms, function ($payform) {
            return $payform->configured();
        });

        // If no currency specified, use current system currency
        if ($currency === null) {
            $currency = CurrencyServices::getSystemCurrencyShortName();
        }

        // Filter by currency support
        return array_filter($configuredPayforms, function ($payform) use ($currency) {
            return in_array($currency, $payform->getCurrencies());
        });
    }
}
