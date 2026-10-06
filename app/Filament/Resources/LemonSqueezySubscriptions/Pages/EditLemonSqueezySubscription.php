<?php

namespace App\Filament\Resources\LemonSqueezySubscriptions\Pages;

use App\Filament\Resources\LemonSqueezySubscriptions\LemonSqueezySubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLemonSqueezySubscription extends EditRecord
{
    protected static string $resource = LemonSqueezySubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
