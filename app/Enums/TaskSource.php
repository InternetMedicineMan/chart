<?php

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Voice = 'voice';
    case Shortcut = 'shortcut';
    case Email = 'email';
    case Observation = 'observation';
    case Template = 'template';
}
