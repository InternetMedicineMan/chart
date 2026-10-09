<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendarCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['state' => ['required', 'string', 'max:128'], 'code' => ['nullable', 'string', 'max:4096'], 'error' => ['nullable', 'string', 'max:100']];
    }
}
