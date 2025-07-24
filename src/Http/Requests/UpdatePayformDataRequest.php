<?php

namespace Ingenius\Payforms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ingenius\Payforms\Exceptions\PayformNotFoundException;
use Ingenius\Payforms\Services\PayformsManager;

class UpdatePayformDataRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payformId' => ['required', 'exists:payforms_data,payform_id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:300'],
            'active' => ['required', 'boolean'],
            'currencies' => ['required', 'array'],
            'currencies.*' => ['required', 'string', 'max:3'],
            ...empty($this->generateArgsRules()) ?
                [] :
                [
                    'args' => ['required', 'array'],
                    ...$this->generateArgsRules()
                ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function generateArgsRules(): array
    {
        $payformId = request('payformId');
        if (!$payformId) {
            return [];
        }

        try {
            $payFormManager = app(PayformsManager::class);
            $payform = $payFormManager->getPayform($payformId);
        } catch (PayformNotFoundException $e) {
            return [];
        }

        $args = [];

        foreach ($payform->rules() as $key => $rule) {
            $args['args.' . $key] = $rule;
        }

        return $args;
    }
}
