<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function migrationEndpoints(): HasMany
    {
        return $this->hasMany(MigrationEndpoint::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
