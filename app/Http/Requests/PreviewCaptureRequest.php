<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:20000'], 'captured_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/']];
    }

    public function messages(): array
    {
        return ['text.required' => 'Enter an utterance to try.', 'captured_at.regex' => 'Include the capture time and its UTC offset.'];
    }
}
