<?php

namespace App\Http\Requests;

use App\Enums\Sphere;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sphere' => ['required', Rule::enum(Sphere::class)],
            'cadence_days' => ['nullable', 'integer', 'between:1,3650'],
            'quiet_enabled' => ['required', 'boolean'],
            'parked' => ['required', 'boolean'],
        ];
    }
}
