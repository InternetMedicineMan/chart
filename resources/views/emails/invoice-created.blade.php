<x-mail::message>
# Invoice {{ $invoice->number }}

Hello {{ $invoice->customer_name }},

Your invoice is attached to this email.

**Total:** {{ strtoupper($invoice->currency) }} {{ number_format($invoice->total / 100, 2) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
