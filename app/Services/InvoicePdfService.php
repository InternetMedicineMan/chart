<?php

namespace App\Services;

use App\Models\Invoice;
use Spatie\Browsershot\Browsershot;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $html = view('invoices.pdf', [
            'invoice' => $invoice->loadMissing(['items', 'user']),
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->pdf();
    }

    public function filename(Invoice $invoice): string
    {
        return (string) str($invoice->number)->slug()->append('.pdf');
    }
}
