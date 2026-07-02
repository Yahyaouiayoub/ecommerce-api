<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'recipient_email',
        'subject',
        'mailable_type',
        'mailer_driver',
        'status',
        'error_message',
        'headers',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
