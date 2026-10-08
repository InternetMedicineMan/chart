<?php

namespace App\Enums;

enum ProjectLifecycle: string
{
    case Someday = 'someday';
    case Active = 'active';
    case Parked = 'parked';
    case Done = 'done';
    case Dropped = 'dropped';
}
