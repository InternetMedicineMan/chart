<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'integer', 'between:1,1000'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'completed' => ['required', 'boolean'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }
}
