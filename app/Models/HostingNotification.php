<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingNotification extends Model
{
    protected $fillable = [
        'website_id',
        'website_name',
        'website_url',
        'event_type',
        'title',
        'message',
        'checked_at',
        'telegram_sent_at',
        'telegram_error',
        'telegram_attempts',
        'telegram_next_attempt_at',
        'read_at',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
            'telegram_next_attempt_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
