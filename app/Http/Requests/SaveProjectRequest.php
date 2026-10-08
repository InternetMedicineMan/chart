<?php

namespace App\Http\Requests;

use App\Enums\ProjectLifecycle;
use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:20000'],
            'domain_id' => ['required', 'integer', Rule::exists('domains', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')->whereNull('archived_at')],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'lifecycle' => ['required', Rule::enum(ProjectLifecycle::class)],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'cadence_days' => ['nullable', 'integer', 'between:1,3650'],
            'quiet_enabled' => ['required', 'boolean'],
        ];
    }
}
