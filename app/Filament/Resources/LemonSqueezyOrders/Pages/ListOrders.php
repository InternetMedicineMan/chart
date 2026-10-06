<?php

namespace App\Filament\Resources\LemonSqueezyOrders\Pages;

use App\Filament\Resources\LemonSqueezyOrders\LemonSqueezyOrderResource;
use App\Filament\Resources\LemonSqueezyOrders\Widgets\OrdersStats;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = LemonSqueezyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrdersStats::class,
        ];
    }
}
