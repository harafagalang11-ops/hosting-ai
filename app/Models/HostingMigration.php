<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingMigration extends Model
{
    protected $fillable = [
        'user_id',
        'source_endpoint_id',
        'target_endpoint_id',
        'website_id',
        'website_name',
        'source',
        'target',
        'status',
        'file_count',
        'database_integrity',
        'http_status',
        'backup_file',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
