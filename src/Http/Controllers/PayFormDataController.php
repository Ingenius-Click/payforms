<?php

namespace Ingenius\Payforms\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Core\Http\Controllers\Controller;
use Ingenius\Payforms\Actions\ListPayformsDataAction;
use Ingenius\Payforms\Http\Requests\UpdatePayformDataRequest;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Services\PayformsManager;
use Ingenius\Payforms\Transformers\PublicPayformDataResource;

class PayFormDataController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayformsManager $payformsManager
    ) {}

    public function actives(): JsonResponse
    {
        $actives = collect($this->payformsManager->getActivePayforms());

        return response()->api(data: PublicPayformDataResource::collection($actives), message: 'Actives payforms fetched successfully');
    }

    public function index(Request $request, ListPayformsDataAction $listPayformsDataAction): JsonResponse
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'update', PayFormData::class);

        $payformsData = $listPayformsDataAction->handle($request->all());

        return response()->api(data: $payformsData, message: 'Payforms fetched successfully');
    }

    public function update(UpdatePayformDataRequest $request): JsonResponse
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'update', PayFormData::class);

        $validated = $request->validated();

        $payformData = PayFormData::where('payform_id', $validated['payformId'])->first();

        $payformData->update($request->validated());

        return response()->api(data: $payformData, message: 'Payform data updated successfully');
    }
}
