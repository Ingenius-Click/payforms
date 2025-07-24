<?php

namespace Ingenius\Payforms\Initializers;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Ingenius\Coins\Services\CurrencyServices;
use Ingenius\Core\Interfaces\TenantInitializer;
use Ingenius\Core\Models\Tenant;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Payforms\CashPayForm;

class PayformsTenantInitializer implements TenantInitializer
{
    /**
     * Initialize a new tenant with required payforms data
     *
     * @param Tenant $tenant
     * @param Command $command
     * @return void
     */
    public function initialize(Tenant $tenant, Command $command): void
    {
        // Update cash payform to include the main coin
        $this->updateCashPayform($command);
    }

    public function initializeViaRequest(Tenant $tenant, Request $request): void
    {
        $this->updateCashPayform(null, $request->enable_cash_payform);
    }

    public function rules(): array
    {
        return [
            'enable_cash_payform' => 'required|boolean',
        ];
    }

    /**
     * Get the priority of this initializer
     * Higher priority initializers run first
     *
     * @return int
     */
    public function getPriority(): int
    {
        // Payforms should run after coins
        return 80;
    }

    /**
     * Get the name of this initializer
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Payment Methods Setup';
    }

    /**
     * Get the package name of this initializer
     *
     * @return string
     */
    public function getPackageName(): string
    {
        return 'payforms';
    }

    /**
     * Update cash payform to include the main coin
     * 
     * @param Command $command
     * @return void
     */
    protected function updateCashPayform(Command $command = null, bool $active = true): void
    {
        if ($command) {
            $command->info('Setting up cash payment method...');
        }

        // Find the cash payform
        $cashPayform = PayFormData::where('payform_id', 'cash')->first();

        // If the cash payform doesn't exist, instantiate it first
        if (!$cashPayform) {
            if ($command) {
                $command->info('Cash payment method not found, creating it...');
            }

            // Instantiating CashPayForm will create the record in the database
            try {
                new CashPayForm();
                // Fetch the newly created record
                $cashPayform = PayFormData::where('payform_id', 'cash')->first();

                if (!$cashPayform) {
                    if ($command) {
                        $command->error('Failed to create cash payment method.');
                    }
                    return;
                }
            } catch (\Exception $e) {
                if ($command) {
                    $command->error('Error creating cash payment method: ' . $e->getMessage());
                }
                return;
            }
        }

        // Find the main coin
        $mainCoin = CurrencyServices::getBaseCurrencyShortName();

        if (!$mainCoin) {
            if ($command) {
                $command->error('Main currency not found.');
            }
            return;
        }

        // Update the cash payform to include the main coin and set it active
        $currencies = $cashPayform->currencies ?? [];

        if (!in_array($mainCoin, $currencies)) {
            $currencies[] = $mainCoin;
        }

        // Ask if cash payment should be active
        if ($command) {
            $active = $command->confirm('Do you want to enable cash payment?', true);
        }

        $cashPayform->update([
            'currencies' => $currencies,
            'active' => $active,
        ]);

        if ($command) {
            $command->info('Cash payment method updated successfully.');
        }
    }
}
