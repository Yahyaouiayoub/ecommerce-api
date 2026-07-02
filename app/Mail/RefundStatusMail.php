<?php

namespace App\Mail;

use App\Models\Refund;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public Refund $refund;

    /**
     * Create a new message instance.
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund->loadMissing(['order', 'items.orderItem.product']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = Setting::getValue('company_name', 'Lumen Store');

        return new Envelope(
            subject: "{$companyName} — Refund {$this->refund->refund_number} {$this->refund->status_label}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-status',
            with: [
                'refund'            => $this->refund,
                'companyName'       => Setting::getValue('company_name', 'Lumen Store'),
                'refundUrl'         => url('/refunds/' . $this->refund->id),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
