<?php

namespace Ingenius\Payforms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ingenius\Orders\Models\OrderStatusTransition;
use Ingenius\Core\Models\Tenant;

class AddOrderStatusTransitionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payform:add-transitions
                           {--tenant= : The ID of a specific tenant to run for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add required order status transitions for PayForm (new -> paid, paid -> completed) for all or specific tenant';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Adding order status transitions for PayForm...');

        $tenantId = $this->option('tenant');

        // Get tenants to process
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID '{$tenantId}' not found.");
                return 1;
            }
            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();
        }

        $totalAddedCount = 0;
        $totalSkippedCount = 0;
        $failedTenants = [];

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->id}");

            // Initialize the tenant
            tenancy()->initialize($tenant);

            try {
                [$addedCount, $skippedCount] = $this->processTenantTransitions();

                $totalAddedCount += $addedCount;
                $totalSkippedCount += $skippedCount;

                $this->info("Completed for tenant {$tenant->id}: {$addedCount} transitions added, {$skippedCount} skipped.");
            } catch (\Exception $e) {
                $this->error("Failed for tenant {$tenant->id}: {$e->getMessage()}");
                $failedTenants[] = $tenant->id;
            } finally {
                // End the tenant context
                tenancy()->end();
            }
        }

        $this->info("Overall completed: {$totalAddedCount} transitions added, {$totalSkippedCount} skipped across " . $tenants->count() . " tenant(s).");

        if (!empty($failedTenants)) {
            $this->error("Failed for " . count($failedTenants) . " tenant(s): " . implode(', ', $failedTenants));
            return 1;
        }

        return 0;
    }

    /**
     * Process transitions for a single tenant
     *
     * @return array [addedCount, skippedCount]
     */
    protected function processTenantTransitions(): array
    {
        $transitions = [
            [
                'from_status' => 'new',
                'to_status' => 'paid',
                'is_enabled' => true,
                'sort_order' => 10,
                'module' => 'PayForm',
            ],
            [
                'from_status' => 'paid',
                'to_status' => 'completed',
                'is_enabled' => true,
                'sort_order' => 10,
                'module' => 'PayForm',
            ],
        ];

        $addedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($transitions as $transitionData) {
                // Check if the transition already exists
                $existingTransition = OrderStatusTransition::where('from_status', $transitionData['from_status'])
                    ->where('to_status', $transitionData['to_status'])
                    ->first();

                if ($existingTransition) {
                    $this->line("  Transition from {$transitionData['from_status']} to {$transitionData['to_status']} already exists. Skipping.");
                    $skippedCount++;
                    continue;
                }

                // Create the transition
                $transition = new OrderStatusTransition();
                $transition->from_status = $transitionData['from_status'];
                $transition->to_status = $transitionData['to_status'];
                $transition->is_enabled = $transitionData['is_enabled'];
                $transition->sort_order = $transitionData['sort_order'];
                $transition->module = $transitionData['module'];
                $transition->save();

                $this->line("  Added transition: {$transitionData['from_status']} -> {$transitionData['to_status']}");
                $addedCount++;
            }

            DB::commit();
            return [$addedCount, $skippedCount];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
