<?php

namespace App\Filament\Resources\StripeSubscriptions\Pages;

use App\Filament\Resources\StripeSubscriptions\StripeSubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStripeSubscription extends CreateRecord
{
    protected static string $resource = StripeSubscriptionResource::class;
}
