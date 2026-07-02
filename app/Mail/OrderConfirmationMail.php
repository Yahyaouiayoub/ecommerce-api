<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing(['items.product', 'address', 'user']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = Setting::getValue('company_name', 'Lumen Store');

        return new Envelope(
            subject: "{$companyName} — Order Confirmation #{$this->order->order_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'order'       => $this->order,
                'companyName' => Setting::getValue('company_name', 'Lumen Store'),
                'subtotal'    => $this->calculateSubtotal(),
                'total'       => (float) $this->order->total_price,
                'customerName' => $this->order->user?->full_name
                    ?? $this->order->address?->full_name
                    ?? $this->order->guest_name
                    ?? 'Valued Customer',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Calculate the subtotal from order items.
     */
    private function calculateSubtotal(): float
    {
        $subtotal = 0;
        foreach ($this->order->items as $item) {
            $subtotal += (float) $item->price * (int) $item->quantity;
        }
        return round($subtotal, 2);
    }
}
