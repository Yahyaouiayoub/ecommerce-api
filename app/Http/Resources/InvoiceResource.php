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
        return [
            'id'               => $this->id,
            'order_id'         => $this->order_id,
            'invoice_number'   => $this->invoice_number,
            'total_amount'     => (float) $this->total_amount,
            'paid_amount'      => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'status'           => $this->status,
            'status_label'     => $this->status_label,
            'total_formatted'  => $this->total_formatted,
            'paid_formatted'   => $this->paid_formatted,
            'remaining_formatted' => $this->remaining_formatted,
            'due_date'         => $this->due_date?->format('Y-m-d'),
            'notes'            => $this->notes,
            'issued_at'        => $this->issued_at?->toISOString(),
            'paid_at'          => $this->paid_at?->toISOString(),
            'created_at'       => $this->created_at->toISOString(),
            'updated_at'       => $this->updated_at->toISOString(),

        ];
    }
}
