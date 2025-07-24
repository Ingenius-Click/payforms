<?php

namespace Ingenius\Payforms\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Services\PayformsManager;

class ListPayformsDataAction
{
    public function __construct(
        protected PayformsManager $payformsManager
    ) {}

    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = PayFormData::query();

        // Filter by tenant-accessible payforms based on features
        $accessiblePayformIds = $this->payformsManager->getTenantAccessiblePayformIds();

        if (empty($accessiblePayformIds)) {
            // If no accessible payforms, return empty pagination
            return $query->whereRaw('1 = 0')->paginate(15);
        }

        $query->whereIn('payform_id', $accessiblePayformIds);

        // Get per page value or default to 15
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
