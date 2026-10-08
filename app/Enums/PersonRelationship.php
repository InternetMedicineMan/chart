<?php

namespace App\Enums;

enum PersonRelationship: string
{
    case Client = 'client';
    case Colleague = 'colleague';
    case Friend = 'friend';
    case Family = 'family';
    case Ministry = 'ministry';
    case Other = 'other';
}
