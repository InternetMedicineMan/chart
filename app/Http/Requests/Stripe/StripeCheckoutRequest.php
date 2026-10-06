<?php

namespace App\Http\Requests\Stripe;

use Illuminate\Foundation\Http\FormRequest;

class StripeCheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'price' => ['required'],
        ];
    }
}
