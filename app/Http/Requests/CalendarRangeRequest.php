<?php

namespace App\Http\Requests;

use App\Services\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $today = CarbonImmutable::parse(app(LocalDate::class)->today($this->user()));

        return ['date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->subDays(7)->toDateString(), 'before_or_equal:'.$today->addDays(83)->toDateString()]];
    }
}
