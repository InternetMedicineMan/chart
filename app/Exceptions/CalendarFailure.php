<?php

namespace App\Exceptions;

use RuntimeException;

class CalendarFailure extends RuntimeException
{
    public function __construct(public int $httpStatus = 0)
    {
        parent::__construct(match ($httpStatus) {
            401 => 'Reconnect Google Calendar in Settings to continue syncing.',
            403 => 'Google Calendar access was denied. Check the calendar permissions and API setup.',
            404 => 'This Google calendar or event is no longer available.',
            409, 412 => 'The event changed in Google. Review its latest version before applying your change.',
            410 => 'Google requested a fresh calendar sync.',
            429 => 'Google Calendar is busy. Sync will retry shortly.',
            default => 'Google Calendar could not complete this request. Your saved changes remain in Chart.',
        });
    }
}
