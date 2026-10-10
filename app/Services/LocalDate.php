<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class LocalDate
{
    public function timezone(User $user): string
    {
        return AppSetting::forUser($user)->where('key', 'timezone')->first()?->value ?? 'America/Chicago';
    }

    public function date(DateTimeInterface|string $timestamp, string $timezone): string
    {
        return CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($timezone)->toDateString();
    }

    public function localTime(string $input, string $timezone, string $field = 'due_time'): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, $timezone);
        if ($time->format('Y-m-d\TH:i') !== $input || $time->addHour()->format('Y-m-d\TH:i') === $input || $time->subHour()->format('Y-m-d\TH:i') === $input) {
            throw ValidationException::withMessages([$field => 'This local time is skipped or repeated by a clock change. Choose an unambiguous time.']);
        }

        return $time;
    }

    public function today(User $user): string
    {
        return $this->date(now(), $this->timezone($user));
    }

    public function daysSince(DateTimeInterface|string $timestamp, string $timezone): int
    {
        return (int) CarbonImmutable::parse($this->date($timestamp, $timezone), 'UTC')
            ->diffInDays(CarbonImmutable::parse($this->date(now(), $timezone), 'UTC'));
    }
}
