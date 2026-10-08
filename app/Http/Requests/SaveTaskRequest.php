<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'domain_id' => ['nullable', 'integer', Rule::exists('domains', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')->whereNull('archived_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'priority' => ['sometimes', 'required', 'integer', 'between:1,4'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('due_time') && ! $this->filled('due_date')) {
                $validator->errors()->add('due_date', 'Choose a date for the due time.');
            }
        }];
    }
}
