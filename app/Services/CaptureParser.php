<?php

namespace App\Services;

use App\Models\Capture;

interface CaptureParser
{
    public function parse(Capture $capture): array;
}
