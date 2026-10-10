<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:100'], 'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys' => ['required', 'array:p256dh,auth'], 'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}$/'], 'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}$/']];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $url = parse_url((string) $this->input('endpoint'));
            $host = strtolower($url['host'] ?? '');
            $allowed = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true) || str_ends_with($host, '.push.services.mozilla.com') || str_ends_with($host, '.notify.windows.com');
            if (! $allowed || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || ($url['port'] ?? 443) !== 443) {
                $validator->errors()->add('endpoint', 'Use a subscription from a supported browser push provider.');
            }
            $key = base64_decode(strtr((string) $this->input('keys.p256dh'), '-_', '+/'), true);
            if (! $key || strlen($key) !== 65 || ord($key[0]) !== 4) {
                $validator->errors()->add('keys.p256dh', 'The browser subscription key is invalid. Enable notifications again.');
            }
        }];
    }
}
