<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TaskRecurrence
{
    /** The supported date-based RRULE subset; unsupported clauses are rejected, never ignored. */
    public function parse(string $rule): array
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim($rule))) as $part) {
            $pair = explode('=', $part);
            if (count($pair) !== 2 || isset($parts[$pair[0]]) || ! in_array($pair[0], ['FREQ', 'INTERVAL', 'UNTIL'], true)) {
                $this->invalid();
            }
            $parts[$pair[0]] = $pair[1];
        }
        if (! in_array($parts['FREQ'] ?? '', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)
            || ! preg_match('/^[1-9][0-9]{0,2}$/', $parts['INTERVAL'] ?? '1') || (int) ($parts['INTERVAL'] ?? 1) > 365) {
            $this->invalid();
        }
        if (isset($parts['UNTIL'])) {
            if (! preg_match('/^([1-9][0-9]{3})([0-9]{2})([0-9]{2})$/', $parts['UNTIL'], $date) || ! checkdate((int) $date[2], (int) $date[3], (int) $date[1])) {
                $this->invalid();
            }
        }

        return ['FREQ' => $parts['FREQ'], 'INTERVAL' => (int) ($parts['INTERVAL'] ?? 1)] + (isset($parts['UNTIL']) ? ['UNTIL' => $parts['UNTIL']] : []);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['recurrence_rule' => 'Choose a daily, weekly, monthly or yearly repeat, an interval of 1–365, and an optional end date. Other recurrence rules are not supported yet.']);
    }

    public function attributes(User $user, array $data, ?Task $task = null): array
    {
        if (! array_key_exists('recurrence_rule', $data)) {
            if ($task?->recurrence_rule && array_key_exists('due_date', $data) && $data['due_date'] !== $task->due_date?->toDateString()) {
                $data['recurrence_rule'] = $task->recurrence_rule;
            } else {
                return [];
            }
        }
        if (blank($data['recurrence_rule'])) {
            return ['recurrence_rule' => null, 'recurrence_anchor' => null, 'recurrence_timezone' => null, 'recurrence_index' => 0];
        }
        $parts = $this->parse($data['recurrence_rule']);
        $due = array_key_exists('due_date', $data) ? $data['due_date'] : $task?->due_date?->toDateString();
        if (! $due || $due < '1000-01-02' || $due > '9998-12-31') {
            throw ValidationException::withMessages(['due_date' => 'Choose the first due date for this repeat.']);
        }
        if (isset($parts['UNTIL']) && $parts['UNTIL'] < str_replace('-', '', $due)) {
            throw ValidationException::withMessages(['recurrence_rule' => 'The repeat end date must be on or after this due date.']);
        }
        $rule = collect($parts)->map(fn ($value, $key) => "$key=$value")->implode(';');
        $unchanged = $task && $task->recurrence_rule === $rule && $task->due_date?->toDateString() === $due;

        return ['recurrence_rule' => $rule, 'recurrence_anchor' => $unchanged ? $task->recurrence_anchor : $due,
            'recurrence_timezone' => $unchanged ? $task->recurrence_timezone : app(LocalDate::class)->timezone($user),
            'recurrence_index' => $unchanged ? $task->recurrence_index : 0];
    }

    /** Keep the original calendar schedule; missed and impossible dates do not produce backlog. */
    public function next(Task $task, CarbonImmutable $completed): ?array
    {
        if (! $task->recurrence_rule) {
            return null;
        }
        $parts = $this->parse($task->recurrence_rule);
        $anchor = CarbonImmutable::parse($task->recurrence_anchor->toDateString(), 'UTC');
        $cutoff = max($task->due_date->toDateString(), $completed->setTimezone($task->recurrence_timezone)->toDateString());
        $after = CarbonImmutable::parse($cutoff, 'UTC');
        $distance = match ($parts['FREQ']) {
            'DAILY' => (int) $anchor->diffInDays($after),
            'WEEKLY' => (int) floor($anchor->diffInDays($after) / 7),
            'MONTHLY' => ($after->year - $anchor->year) * 12 + $after->month - $anchor->month,
            'YEARLY' => $after->year - $anchor->year,
        };
        $start = max($task->recurrence_index + 1, (int) floor($distance / $parts['INTERVAL']));
        for ($index = $start; $index < $start + 5000; $index++) {
            $offset = $index * $parts['INTERVAL'];
            $candidate = match ($parts['FREQ']) {
                'DAILY' => $anchor->addDays($offset),
                'WEEKLY' => $anchor->addWeeks($offset),
                'MONTHLY' => $anchor->startOfMonth()->addMonths($offset),
                'YEARLY' => $anchor->startOfYear()->addYears($offset)->month($anchor->month),
            };
            if ($candidate->year > 9998) {
                return null;
            }
            if (in_array($parts['FREQ'], ['MONTHLY', 'YEARLY'], true)) {
                if ($anchor->day > $candidate->daysInMonth) {
                    continue;
                }
                $candidate = $candidate->day($anchor->day);
            }
            if (isset($parts['UNTIL']) && $candidate->format('Ymd') > $parts['UNTIL']) {
                return null;
            }
            if ($candidate->toDateString() <= $cutoff) {
                continue;
            }
            if ($task->due_time) {
                $input = $candidate->toDateString().'T'.substr($task->due_time, 0, 5);
                if (CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, $task->recurrence_timezone)->format('Y-m-d\TH:i') !== $input) {
                    continue;
                }
            }

            return ['due_date' => $candidate->toDateString(), 'recurrence_index' => $index];
        }
        throw ValidationException::withMessages(['recurrence_rule' => 'No valid next occurrence could be found. Edit the repeat before completing.']);
    }
}
