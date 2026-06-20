<?php

namespace App\Mail;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $items;
    public ?User $cartOwner;
    public string $customerName;
    public int $itemCount;
    public float $totalValue;
    public string $ownerKey;

    /**
     * Create a new message instance.
     */
    public function __construct(string $ownerKey, Collection $items, ?User $cartOwner)
    {
        $this->ownerKey = $ownerKey;
        $this->items = $items;
        $this->cartOwner = $cartOwner;
        $this->customerName = $cartOwner
            ? ($cartOwner->full_name ?? "{$cartOwner->first_name} {$cartOwner->last_name}")
            : 'Guest';
        $this->itemCount = $items->sum('quantity');
        $this->totalValue = $items->sum(function ($item) {
            return ($item->product->price ?? 0) * $item->quantity;
        });
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->cartOwner
            ? "You left items in your cart — don't miss out!"
            : "Guest cart abandoned — {$this->itemCount} item(s) left behind";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-cart',
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
}
