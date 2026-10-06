<?php

namespace App\Filament\Resources\StripeOrders\Pages;

use App\Filament\Resources\StripeOrders\StripeOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeOrder extends EditRecord
{
    protected static string $resource = StripeOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
