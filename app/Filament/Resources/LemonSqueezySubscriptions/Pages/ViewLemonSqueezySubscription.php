<?php

namespace App\Filament\Resources\LemonSqueezySubscriptions\Pages;

use App\Filament\Resources\LemonSqueezySubscriptions\LemonSqueezySubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLemonSqueezySubscription extends ViewRecord
{
    protected static string $resource = LemonSqueezySubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
