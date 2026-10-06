<?php

namespace App\Filament\Resources\StripeSubscriptions\Pages;

use App\Filament\Resources\StripeSubscriptions\StripeSubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStripeSubscription extends ViewRecord
{
    protected static string $resource = StripeSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
