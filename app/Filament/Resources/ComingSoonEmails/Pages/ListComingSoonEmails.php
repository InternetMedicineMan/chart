<?php

namespace App\Filament\Resources\ComingSoonEmails\Pages;

use App\Filament\Resources\ComingSoonEmails\ComingSoonEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComingSoonEmails extends ListRecords
{
    protected static string $resource = ComingSoonEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
