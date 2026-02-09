<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $table = 'players';

    protected $fillable = [
        'game_code',
        'steam_id',
        'last_name',
        'skill',
        'kills',
        'deaths',
        'headshots',
        'hide_ranking',
        'connection_time',
        'last_event',
    ];

    protected $casts = [
        'skill' => 'float',
        'kills' => 'integer',
        'deaths' => 'integer',
        'headshots' => 'integer',
        'hide_ranking' => 'boolean',
        'connection_time' => 'integer',
        'last_event' => 'datetime',
    ];

    // Relationships
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_code', 'code');
    }

    public function kills(): HasMany
    {
        return $this->hasMany(EventFrag::class, 'killer_id');
    }

    public function deaths(): HasMany
    {
        return $this->hasMany(EventFrag::class, 'victim_id');
    }

    // Accessors
    public function getKdRatioAttribute(): float
    {
        if ($this->deaths === 0) {
            return (float) $this->kills;
        }

        return round($this->kills / $this->deaths, 2);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('hide_ranking', false);
    }

    public function scopeByGame($query, string $game)
    {
        return $query->where('game_code', $game);
    }

    public function scopeTopRanked($query)
    {
        return $query->orderBy('skill', 'desc');
    }
}
