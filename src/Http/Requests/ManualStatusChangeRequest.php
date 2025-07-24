<?php

namespace Ingenius\Payforms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ingenius\Payforms\Enums\PaymentStatus;

class ManualStatusChangeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $statuses = array_map(fn($status) => $status->value, PaymentStatus::cases());

        return [
            'status' => ['required', 'string', Rule::in($statuses)],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
