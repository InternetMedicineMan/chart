<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveWorkWaitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'waiting' => ['required', 'boolean'],
            'revision' => ['required', 'integer', 'min:0'],
            'person_id' => ['nullable', 'integer', 'prohibits:new_person_name', Rule::requiredIf(fn () => $this->boolean('waiting') && ! $this->filled('new_person_name')), Rule::prohibitedIf(fn () => ! $this->boolean('waiting')), Rule::exists('people', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'new_person_name' => ['nullable', 'string', 'max:100', 'prohibits:person_id', Rule::prohibitedIf(fn () => ! $this->boolean('waiting'))],
            'expected_by' => ['nullable', 'date_format:Y-m-d', Rule::prohibitedIf(fn () => ! $this->boolean('waiting'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_person_name'))) {
            $this->merge(['new_person_name' => Str::squish($this->input('new_person_name')) ?: null]);
        }
    }

    public function messages(): array
    {
        return [
            'person_id.required' => 'Choose a person or add a name for this wait.',
            'person_id.exists' => 'Choose an available person from your workspace.',
            'person_id.prohibits' => 'Choose an existing person or add a new name, not both.',
            'expected_by.date_format' => 'Choose a valid expected response date.',
        ];
    }
}
