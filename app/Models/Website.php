<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'migration_endpoint_id',
        'name',
        'url',
        'document_root',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function migrationEndpoint(): BelongsTo
    {
        return $this->belongsTo(MigrationEndpoint::class);
    }

    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(HostingNotification::class);
    }
}
