<?php

namespace App\Filament\Resources\ComingSoonEmails\Pages;

use App\Filament\Resources\ComingSoonEmails\ComingSoonEmailResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditComingSoonEmail extends EditRecord
{
    protected static string $resource = ComingSoonEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
