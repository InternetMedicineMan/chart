<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'calendar_id' => ['required', 'integer', Rule::exists('connected_calendars', 'id')->where('user_id', $this->user()->id)],
            'revision' => ['nullable', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:250'], 'description' => ['nullable', 'string', 'max:10000'], 'location' => ['nullable', 'string', 'max:2000'],
            'all_day' => ['required', 'boolean'],
            'starts_at' => ['exclude_if:all_day,true', 'required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['exclude_if:all_day,true', 'required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'start_date' => ['exclude_if:all_day,false', 'required', 'date_format:Y-m-d'],
            'end_date' => ['exclude_if:all_day,false', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return ['calendar_id.exists' => 'Choose one of your connected calendars.', 'ends_at.after' => 'The end must be after the start.', 'end_date.after_or_equal' => 'The last day must be on or after the first day.'];
    }
}
