<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFrag extends Model
{
    /** @use HasFactory<\Database\Factories\EventFragFactory> */
    use HasFactory;

    protected $table = 'event_frags';

    protected $fillable = [
        'killer_id',
        'victim_id',
        'server_id',
        'weapon_code',
        'headshot',
        'map',
        'event_time',
        'pos_x',
        'pos_y',
        'pos_z',
    ];

    protected $casts = [
        'headshot' => 'boolean',
        'event_time' => 'datetime',
        'pos_x' => 'integer',
        'pos_y' => 'integer',
        'pos_z' => 'integer',
    ];

    // Relationships
    public function killer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'killer_id');
    }

    public function victim(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'victim_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function weapon(): BelongsTo
    {
        return $this->belongsTo(Weapon::class, 'weapon_code', 'code');
    }

    // Scopes
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('event_time', '>=', now()->subDays($days));
    }

    public function scopeHeadshotsOnly($query)
    {
        return $query->where('headshot', true);
    }

    public function scopeByMap($query, string $map)
    {
        return $query->where('map', $map);
    }
}
