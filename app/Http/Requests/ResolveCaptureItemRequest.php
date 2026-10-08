<?php

namespace App\Http\Requests;

use App\Services\CaptureActions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveCaptureItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_diff(array_keys(CaptureActions::DEFINITIONS), ['needs_triage']))],
            'title' => ['nullable', 'required_if:type,create_task,create_project', 'string', 'max:100'],
            'body' => ['nullable', 'required_if:type,capture_idea', 'string', 'max:20000'],
            'domain_ref' => ['nullable', 'string', 'max:100'], 'project_ref' => ['nullable', 'string', 'max:100'],
            'domain_id' => ['nullable', 'integer', Rule::exists('domains', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')->whereNull('archived_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()->id)->where('lifecycle', 'active')->whereNull('deleted_at')],
            'due_date' => ['nullable', 'date_format:Y-m-d'], 'due_time' => ['nullable', 'date_format:H:i'],
            'priority' => ['nullable', 'integer', 'between:1,4'],
            'target_date' => ['nullable', 'date_format:Y-m-d'], 'lifecycle' => ['nullable', Rule::in(['active', 'someday'])],
        ];
    }

    public function messages(): array
    {
        return ['type.in' => 'Choose a task, idea or project.', 'title.required_if' => 'Give this item a short name.'];
    }
}
