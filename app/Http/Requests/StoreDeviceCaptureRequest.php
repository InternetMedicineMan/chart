<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreDeviceCaptureRequest extends StoreCaptureRequest
{
    public function rules(): array
    {
        return [
            'text' => parent::rules()['text'],
            'source' => ['required', Rule::in(['ios', 'watch', 'mac', 'android', 'in_app', 'offline_queue'])],
            'device_label' => ['nullable', 'string', 'max:100'],
            'captured_at' => ['sometimes', 'required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/'],
            'request_key' => ['sometimes', 'required', 'uuid'],
            'mode' => ['sometimes', Rule::in(['single', 'dump'])],
            'wait' => ['sometimes', 'boolean'],
        ];
    }
}
