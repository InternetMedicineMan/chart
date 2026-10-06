<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Symfony\Component\HttpFoundation\Response;

class DownloadInvoiceController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePdfService $pdf): Response
    {
        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
        ]);
    }
}
