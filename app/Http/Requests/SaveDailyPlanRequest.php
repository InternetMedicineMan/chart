<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveDailyPlanRequest extends FormRequest
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
            'plan_date' => ['required', 'date_format:Y-m-d'],
            'revision' => ['required', 'integer', 'min:0'],
            'top_task_ids' => ['present', 'array', 'max:3', 'list'],
            'top_task_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'tomorrow_focus' => ['present', 'nullable', 'string', 'max:280'],
        ];
    }

    public function messages(): array
    {
        return [
            'top_task_ids.max' => 'Choose up to three tasks for today.',
            'top_task_ids.*.distinct' => 'Choose each task only once.',
            'tomorrow_focus.max' => 'Keep tomorrow’s focus to 280 characters.',
        ];
    }
}
