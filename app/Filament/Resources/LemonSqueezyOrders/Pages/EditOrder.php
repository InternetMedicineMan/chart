<?php

namespace App\Filament\Resources\LemonSqueezyOrders\Pages;

use App\Filament\Resources\LemonSqueezyOrders\LemonSqueezyOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = LemonSqueezyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
