<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $table = 'servers';

    protected $fillable = [
        'game_code',
        'name',
        'address',
        'port',
        'public_address',
        'enabled',
        'map',
        'last_activity',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'last_activity' => 'datetime',
    ];

    // Relationships
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_code', 'code');
    }

    public function eventFrags(): HasMany
    {
        return $this->hasMany(EventFrag::class, 'server_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeByGame($query, string $game)
    {
        return $query->where('game_code', $game);
    }

    public function scopeOnline($query, int $minutes = 5)
    {
        return $query->where('last_activity', '>=', now()->subMinutes($minutes));
    }
}
