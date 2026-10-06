<?php

namespace App\Filament\Resources\StripeSubscriptions\Pages;

use App\Filament\Resources\StripeSubscriptions\StripeSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStripeSubscriptions extends ListRecords
{
    protected static string $resource = StripeSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
