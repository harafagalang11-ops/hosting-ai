<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationEndpoint extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'host',
        'port',
        'connection_type',
        'username',
        'credential',
        'host_key_fingerprint',
    ];

    protected $hidden = [
        'credential',
    ];

    protected function casts(): array
    {
        return [
            'credential' => 'encrypted',
            'port' => 'integer',
        ];
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
