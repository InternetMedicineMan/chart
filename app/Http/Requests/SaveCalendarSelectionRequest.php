<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCalendarSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(['off', 'read_only', 'two_way'])], 'revision' => ['required', 'integer', 'min:0']];
    }

    public function messages(): array
    {
        return ['mode.in' => 'Choose off, read-only or two-way.'];
    }
}
