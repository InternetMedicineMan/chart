<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['dismiss', 'snooze', 'restore'])], 'days' => ['required_if:action,snooze', 'nullable', 'integer', Rule::in([1, 7])]];
    }

    public function messages(): array
    {
        return ['days.in' => 'Snooze for one or seven days.', 'action.in' => 'Choose dismiss, snooze or restore.'];
    }
}
