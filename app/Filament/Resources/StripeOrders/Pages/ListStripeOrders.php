<?php

namespace App\Filament\Resources\StripeOrders\Pages;

use App\Filament\Resources\StripeOrders\StripeOrderResource;
use App\Filament\Resources\StripeOrders\Widgets\OrdersStats;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListStripeOrders extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = StripeOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrdersStats::class,
        ];
    }
}
