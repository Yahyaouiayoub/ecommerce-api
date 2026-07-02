<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_id',
        'image_path',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }
}
