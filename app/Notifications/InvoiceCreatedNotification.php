<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(InvoicePdfService::class);

        return (new MailMessage)
            ->subject('Invoice '.$this->invoice->number)
            ->markdown('emails.invoice-created', [
                'invoice' => $this->invoice->loadMissing('items'),
                'user' => $notifiable,
            ])
            ->attachData($pdf->render($this->invoice), $pdf->filename($this->invoice), [
                'mime' => 'application/pdf',
            ]);
    }
}
