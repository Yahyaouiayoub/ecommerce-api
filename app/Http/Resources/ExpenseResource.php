<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'amount'          => (float) $this->amount,
            'amount_formatted' => $this->amount_formatted,
            'category'        => $this->category,
            'category_label'  => $this->category_label,
            'note'            => $this->note,
            'description'     => $this->description,
            'expense_date'    => $this->expense_date?->format('Y-m-d'),
            'created_by'      => $this->created_by,
            'creator'         => $this->whenLoaded('creator', function () {
                return [
                    'id'        => $this->creator->id,
                    'full_name' => $this->creator->full_name,
                    'email'     => $this->creator->email,
                ];
            }),
            'created_at'      => $this->created_at->toISOString(),
            'updated_at'      => $this->updated_at->toISOString(),
        ];
    }
}
