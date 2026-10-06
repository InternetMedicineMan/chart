<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_invoices_assigns_global_invoice_numbers(): void
    {
        $first = Invoice::create([
            'customer_name' => 'First Customer',
            'customer_email' => 'first@example.com',
            'issued_at' => now(),
            'currency' => 'usd',
        ]);

        $second = Invoice::create([
            'customer_name' => 'Second Customer',
            'customer_email' => 'second@example.com',
            'issued_at' => now(),
            'currency' => 'usd',
        ]);

        $this->assertSame('INV-000001', $first->number);
        $this->assertSame('INV-000002', $second->number);
    }

    public function test_invoice_item_totals_are_calculated_and_rolled_up(): void
    {
        $invoice = Invoice::create([
            'customer_name' => 'Acme Inc.',
            'customer_email' => 'billing@example.com',
            'issued_at' => now(),
            'currency' => 'usd',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Subscription',
            'quantity' => 2,
            'unit_price' => 1000,
            'tax_rate' => 10,
        ]);

        $invoice->refresh();

        $this->assertSame(2000, $invoice->subtotal);
        $this->assertSame(200, $invoice->tax_total);
        $this->assertSame(2200, $invoice->total);
    }

    public function test_invoice_download_route_returns_pdf_response(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::create([
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'issued_at' => now(),
            'currency' => 'usd',
        ]);

        $this->mock(InvoicePdfService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('render')
                ->once()
                ->with(Mockery::on(fn ($given) => $given->is($invoice)))
                ->andReturn('%PDF-1.4');

            $mock->shouldReceive('filename')
                ->once()
                ->with(Mockery::on(fn ($given) => $given->is($invoice)))
                ->andReturn('inv-000001.pdf');
        });

        $response = $this->actingAs($user)->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-1.4', $response->getContent());
    }
}
