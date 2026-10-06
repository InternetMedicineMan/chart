<?php

namespace App\Filament\Resources\StripeSubscriptions\Pages;

use App\Filament\Resources\StripeSubscriptions\StripeSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeSubscription extends EditRecord
{
    protected static string $resource = StripeSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
