<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Weapon extends Model
{
    /** @use HasFactory<\Database\Factories\WeaponFactory> */
    use HasFactory;

    protected $table = 'weapons';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'game_code',
        'name',
        'modifier',
        'enabled',
    ];

    protected $casts = [
        'modifier' => 'float',
        'enabled' => 'boolean',
    ];

    // Relationships
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_code', 'code');
    }

    public function eventFrags(): HasMany
    {
        return $this->hasMany(EventFrag::class, 'weapon_code', 'code');
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

    public function scopeHighModifier($query, float $threshold = 1.0)
    {
        return $query->where('modifier', '>=', $threshold);
    }
}
