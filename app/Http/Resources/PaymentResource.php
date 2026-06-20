<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'invoice_id'     => $this->invoice_id,
            'order_id'       => $this->order_id,
            'amount'         => (float) $this->amount,
            'amount_formatted' => $this->amount_formatted,
            'payment_method' => $this->payment_method,
            'payment_type'   => $this->payment_type,
            'payment_type_label' => $this->payment_type_label,
            'status'         => $this->status,
            'status_label'   => $this->status_label,
            'transaction_id' => $this->transaction_id,
            'paid_at'        => $this->paid_at?->toISOString(),
            'created_at'     => $this->created_at->toISOString(),
            'invoice'        => new InvoiceResource($this->whenLoaded('invoice')),
        ];
    }
}
