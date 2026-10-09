<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return ['revision' => ['required', 'integer', 'min:1']];
        }

        return [
            'subject_type' => ['required', Rule::in(['project', 'domain'])],
            'subject_id' => ['required', 'integer'],
            'entry' => ['required', 'string', 'max:10000'],
            'minutes' => ['nullable', 'integer', 'between:1,1440'],
            'occurred_local' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:1000-01-02'],
            'timezone' => ['required', 'timezone'],
            'request_key' => ['required', 'uuid'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->entry)) {
            $this->merge(['entry' => trim($this->entry)]);
        }
    }
}
