<?php

namespace Ingenius\Payforms\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Core\Http\Controllers\Controller;
use Ingenius\Payforms\Actions\ListPayformsDataAction;
use Ingenius\Payforms\Http\Requests\UpdatePayformDataRequest;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Services\PayformsManager;
use Ingenius\Payforms\Transformers\PayFormDataResource;
use Ingenius\Payforms\Transformers\PublicPayformDataResource;
use Ingenius\Payforms\Transformers\PayFormDataShowResource;

class PayFormDataController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayformsManager $payformsManager
    ) {}

    public function actives(): JsonResponse
    {
        $actives = collect($this->payformsManager->getActivePayforms());

        return Response::api(data: PublicPayformDataResource::collection($actives), message: 'Actives payforms fetched successfully');
    }

    public function index(Request $request, ListPayformsDataAction $listPayformsDataAction): JsonResponse
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'update', PayFormData::class);

        $payformsData = $listPayformsDataAction->handle($request->all());

        return Response::api(data: PayFormDataResource::collection($payformsData), message: 'Payforms fetched successfully');
    }

    public function update(UpdatePayformDataRequest $request, PayFormData $payFormData): JsonResponse
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'update', PayFormData::class);

        $accesiblePayformsIds = $this->payformsManager->getTenantAccessiblePayformIds();

        if (empty($accesiblePayformsIds)) {
            abort(403);
        }

        if (!in_array($payFormData->payform_id, $accesiblePayformsIds)) {
            abort(403);
        }

        $validated = $request->validated();

        $payFormData->fill($validated);

        if (isset($validated['icon'])) {
            $this->saveBase64Image($validated['icon'], $payFormData);
        }

        $payFormData->save();

        return Response::api(data: $payFormData, message: 'Payform data updated successfully');
    }

    public function show(Request $request, PayFormData $payFormData): JsonResponse
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'update', PayFormData::class);

        $accesiblePayformsIds = $this->payformsManager->getTenantAccessiblePayformIds();

        if (empty($accesiblePayformsIds)) {
            abort(403);
        }

        if (!in_array($payFormData->payform_id, $accesiblePayformsIds)) {
            abort(403);
        }

        return Response::api(data: new PayFormDataShowResource($payFormData), message: 'Payform data fetched successfully');
    }

    private function saveBase64Image(string $base64Image, PayFormData $payFormData): void
    {
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Image, $matches)) {
            $oldPath = $payFormData->icon;
            $extension = $matches[1];
            $imageData = base64_decode($matches[2]);

            if ($oldPath && Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }

            $filename = $payFormData->payform_id . '_icon.' . $extension;
            $path = 'payforms/images/' . $filename;

            Storage::put($path, $imageData);

            $payFormData->icon = $path;
        }
    }
}
