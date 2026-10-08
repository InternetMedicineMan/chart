<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:20000', function ($attribute, $value, $fail) {
                if (trim($value) === '') {
                    $fail('Write something to capture.');
                }
            }],
            'request_key' => ['required', 'uuid'],
            'captured_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/'],
            'source' => ['sometimes', Rule::in(['in_app', 'offline_queue'])],
            'mode' => ['sometimes', Rule::in(['single', 'dump'])],
            'owner_id' => ['required', 'integer', Rule::in([$this->user()->id])],
        ];
    }

    public function messages(): array
    {
        return ['owner_id.in' => 'Sign in as the owner who saved this capture before sending it.', 'captured_at.regex' => 'Include the capture time and its UTC offset.', 'text.max' => 'Keep each capture under 20,000 characters.'];
    }
}
