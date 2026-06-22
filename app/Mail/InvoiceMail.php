<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice->loadMissing(['order.items.product', 'order.address', 'order.user', 'payments']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = Setting::getValue('company_name', 'Lumen Store');

        return new Envelope(
            subject: "{$companyName} — Invoice {$this->invoice->invoice_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'companyName' => Setting::getValue('company_name', 'Lumen Store'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        // Generate the PDF inline
        $order = $this->invoice->order;
        $subtotal = 0;
        foreach ($order->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        $shipping = 0;
        $tax = 0;
        $totalOrderPrice = (float) $order->total_price;

        if ($subtotal > 0 && $totalOrderPrice > $subtotal) {
            $difference = $totalOrderPrice - $subtotal;
            $taxSettings = Setting::getTaxSettings();
            if ($taxSettings['enabled']) {
                if ($taxSettings['type'] === 'percentage') {
                    $tax = round($subtotal * ($taxSettings['rate'] / 100), 2);
                    $shipping = round($difference - $tax, 2);
                } else {
                    $tax = round($taxSettings['rate'], 2);
                    $shipping = round($difference - $tax, 2);
                }
            } else {
                $shipping = $difference;
            }
        }

        $settings = (object) [
            'company_name' => Setting::getValue('company_name', 'Lumen Store'),
            'company_address' => Setting::getValue('company_address', '123 Commerce Street'),
            'company_city' => Setting::getValue('company_city', 'Casablanca'),
            'company_country' => Setting::getValue('company_country', 'Morocco'),
            'company_phone' => Setting::getValue('company_phone', '+212 5XX-XXXXXX'),
            'company_email' => Setting::getValue('company_email', 'contact@lumenstore.com'),
        ];

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice'  => $this->invoice,
            'order'    => $order,
            'items'    => $order->items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax'      => $tax,
            'payments' => $this->invoice->payments,
            'settings' => $settings,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "invoice-{$this->invoice->invoice_number}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
