<?php

namespace App\Filament\Resources\ComingSoonEmails\Pages;

use App\Filament\Resources\ComingSoonEmails\ComingSoonEmailResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewComingSoonEmail extends ViewRecord
{
    protected static string $resource = ComingSoonEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
