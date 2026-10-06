<?php

namespace App\Filament\Resources\StripeOrders\Pages;

use App\Filament\Resources\StripeOrders\StripeOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStripeOrder extends CreateRecord
{
    protected static string $resource = StripeOrderResource::class;
}
