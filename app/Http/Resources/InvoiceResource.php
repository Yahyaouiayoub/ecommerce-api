<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the invoice into an API response.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'               => $this->id,
            'order_id'         => $this->order_id,
            'invoice_number'   => $this->invoice_number,
            'total_amount'     => (float) $this->total_amount,
            'paid_amount'      => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'status'           => $this->status,
            'status_label'     => $this->status_label,
            'status_color'     => $this->status_color,
            'total_formatted'  => $this->total_formatted,
            'paid_formatted'   => $this->paid_formatted,
            'remaining_formatted' => $this->remaining_formatted,
            'due_date'         => $this->due_date?->format('Y-m-d'),
            'notes'            => $this->notes,
            'billing_name'     => $this->billing_name,
            'billing_email'    => $this->billing_email,
            'billing_phone'    => $this->billing_phone,
            'billing_address'  => $this->billing_address,
            'payment_method'   => $this->payment_method,
            'issued_at'        => $this->issued_at?->toISOString(),
            'paid_at'          => $this->paid_at?->toISOString(),
            'created_at'       => $this->created_at->toISOString(),
            'updated_at'       => $this->updated_at->toISOString(),
        ];

        // Include order relationship when loaded
        if ($this->relationLoaded('order')) {
            $order = $this->order;
            $data['order'] = [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'total_price'  => (float) $order->total_price,
                'status'       => $order->status,
                'status_label' => $order->status_label,
                'created_at'   => $order->created_at->toISOString(),
            ];

            if ($order->relationLoaded('user') && $order->user) {
                $data['order']['customer'] = [
                    'id'         => $order->user->id,
                    'full_name'  => $order->user->full_name,
                    'email'      => $order->user->email,
                    'phone'      => $order->user->phone,
                ];
            }

            if ($order->relationLoaded('address') && $order->address) {
                $data['order']['address'] = [
                    'full_name'    => $order->address->full_name,
                    'address_line1' => $order->address->address_line1,
                    'address_line2' => $order->address->address_line2,
                    'city'         => $order->address->city,
                    'state'        => $order->address->state,
                    'postal_code'  => $order->address->postal_code,
                    'country'      => $order->address->country,
                    'email'        => $order->address->email,
                    'phone'        => $order->address->phone,
                ];
            }

            if ($order->relationLoaded('items')) {
                $data['order']['items'] = $order->items->map(function ($item) {
                    return [
                        'id'            => $item->id,
                        'product_id'    => $item->product_id,
                        'product_name'  => $item->product?->name ?? 'Unknown Product',
                        'quantity'      => $item->quantity,
                        'price'         => (float) $item->price,
                        'price_formatted' => number_format($item->price, 2) . ' MAD',
                        'subtotal'      => (float) $item->subtotal,
                        'subtotal_formatted' => $item->subtotal_formatted,
                    ];
                });
            }
        }

        // Include payments when loaded
        if ($this->relationLoaded('payments')) {
            $data['payments'] = PaymentResource::collection($this->payments);
        }

        return $data;
    }
}
