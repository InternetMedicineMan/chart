<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::downloadAction(),
            InvoiceResource::emailAction(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Invoice $record */
        $record = $this->record;

        $record->recalculateTotals();
    }
}
