<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLog extends Model
{
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected $fillable = [
        'website_id',
        'website_name',
        'website_url',
        'status',
        'http_code',
        'response_time',
        'detail',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'response_time' => 'decimal:3',
    ];
}
