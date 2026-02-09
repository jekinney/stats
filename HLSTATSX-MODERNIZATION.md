# HLstatsX Modernization Guide
## Recreating HLstatsX with Laravel & Vue.js SPA

---

## Executive Summary

**HLstatsX Community Edition** is a real-time statistics and ranking system for Source engine games (Counter-Strike, Team Fortress 2, Left 4 Dead, etc.). The current implementation uses:
- **Backend**: Perl daemon for log parsing + PHP frontend
- **Database**: MySQL/MariaDB
- **Frontend**: Server-rendered PHP pages with jQuery
- **Plugins**: SourceMod/AMX Mod X game server plugins

This document outlines a modernization strategy using Laravel + Vue.js while maintaining compatibility with existing game servers.

---

## 1. System Architecture Overview

### Current Architecture

```
┌─────────────────┐
│  Game Servers   │
│  (Source Engine)│
└────────┬────────┘
         │ UDP Log Stream
         ▼
┌─────────────────┐     ┌──────────────┐
│   Perl Daemon   │────▶│    MySQL     │
│  (hlstats.pl)   │     │   Database   │
└─────────────────┘     └──────┬───────┘
                               │
                        ┌──────▼───────┐
                        │ PHP Frontend │
                        │ (hlstats.php)│
                        └──────────────┘
```

### Proposed Modern Architecture

```
┌─────────────────┐
│  Game Servers   │
│  (Source Engine)│
└────────┬────────┘
         │ UDP Log Stream
         ▼
┌─────────────────────┐     ┌──────────────────┐
│   Node.js Service   │────▶│  PostgreSQL/     │
│  (Log Parser with   │     │  MySQL Database  │
│   Socket.IO/Redis)  │     └──────┬───────────┘
└─────────────────────┘            │
         │ WebSocket/SSE            │
         ▼                          ▼
┌─────────────────────┐     ┌──────────────────┐
│   Laravel 11 API    │────▶│   Redis Cache    │
│  (REST + Broadcasting)│    │  & Queue System  │
└─────────────────────┘     └──────────────────┘
         │ REST API
         ▼
┌─────────────────────┐
│   Vue 3 SPA with    │
│   Inertia.js        │
│   (TailwindCSS)     │
└─────────────────────┘
```

---

## 2. Core Features Analysis

### 2.1 Database Schema (Key Tables)

Based on `install.sql`, the main entities are:

#### Players
- **hlstats_Players**: Player profiles, stats, rankings
- **hlstats_PlayerUniqueIds**: Steam IDs (multiple per player)
- **hlstats_PlayerPositions**: Historical rank positions
- **hlstats_PlayerWeapons**: Per-weapon statistics
- **hlstats_PlayerSessions**: Play sessions

#### Games & Servers
- **hlstats_Games**: Game configurations (tf, css, csgo, etc.)
- **hlstats_Servers**: Game server registry
- **hlstats_Maps**: Map statistics
- **hlstats_Weapons**: Weapon definitions per game

#### Events & Actions
- **hlstats_Events_Kills**: Kill events log
- **hlstats_Events_Frags**: Player vs Player kills
- **hlstats_Actions**: Game-specific actions (captures, defuses, etc.)
- **hlstats_Events_Entries**: Player connect/disconnect
- **hlstats_Events_Chat**: Chat messages

#### Rankings & Awards
- **hlstats_Awards**: Award definitions
- **hlstats_Awards_Daily**: Daily awards
- **hlstats_Awards_Players**: Player award history
- **hlstats_Ribbons**: Achievement ribbons

#### Clans
- **hlstats_Clans**: Clan information
- **hlstats_PlayerClanHistory**: Clan membership history

#### GeoIP
- **geoLiteCity_Blocks**: IP ranges
- **geoLiteCity_Location**: Location data

---

## 3. Laravel Backend Implementation

### 3.1 Project Structure

```
app/
├── Models/
│   ├── Player.php
│   ├── Server.php
│   ├── Game.php
│   ├── Weapon.php
│   ├── Map.php
│   ├── Clan.php
│   ├── Award.php
│   ├── Event/
│   │   ├── Kill.php
│   │   ├── Chat.php
│   │   └── Action.php
│   └── Stats/
│       ├── PlayerStats.php
│       ├── WeaponStats.php
│       └── MapPerformance.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── PlayerController.php
│   │   │   ├── ServerController.php
│   │   │   ├── StatsController.php
│   │   │   ├── RankingController.php
│   │   │   └── LiveStatsController.php
│   │   └── Web/
│   │       ├── DashboardController.php
│   │       ├── PlayerController.php
│   │       └── ServerController.php
│   └── Resources/
│       ├── PlayerResource.php
│       ├── ServerResource.php
│       └── StatsResource.php
├── Services/
│   ├── StatsCalculator.php
│   ├── RankingService.php
│   ├── AwardProcessor.php
│   └── GeoIPService.php
├── Jobs/
│   ├── ProcessGameEvent.php
│   ├── UpdatePlayerRankings.php
│   ├── CalculateDailyAwards.php
│   └── GenerateHeatmap.php
├── Events/
│   ├── PlayerKilled.php
│   ├── PlayerConnected.php
│   └── ServerUpdated.php
└── Listeners/
    ├── UpdatePlayerStats.php
    └── BroadcastLiveStats.php
```

### 3.2 Key Models

#### Player Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    protected $table = 'hlstats_Players';
    protected $primaryKey = 'playerId';
    
    protected $fillable = [
        'lastName',
        'game',
        'skill',
        'kills',
        'deaths',
        'connection_time',
        'country',
        'city',
        'hideranking',
    ];
    
    protected $casts = [
        'skill' => 'decimal:2',
        'hideranking' => 'boolean',
        'connection_time' => 'integer',
    ];
    
    protected $appends = ['kd_ratio', 'rank'];
    
    // Relationships
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game', 'code');
    }
    
    public function uniqueIds(): HasMany
    {
        return $this->hasMany(PlayerUniqueId::class, 'playerId');
    }
    
    public function weapons(): HasMany
    {
        return $this->hasMany(PlayerWeapon::class, 'playerId');
    }
    
    public function sessions(): HasMany
    {
        return $this->hasMany(PlayerSession::class, 'playerId');
    }
    
    public function awards(): BelongsToMany
    {
        return $this->belongsToMany(Award::class, 'hlstats_Awards_Players')
            ->withPivot(['d_winner_count', 'g_winner_count'])
            ->withTimestamps();
    }
    
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'clan');
    }
    
    // Computed Properties
    public function getKdRatioAttribute(): float
    {
        return $this->deaths > 0 
            ? round($this->kills / $this->deaths, 2) 
            : $this->kills;
    }
    
    public function getRankAttribute(): int
    {
        return static::where('game', $this->game)
            ->where('hideranking', 0)
            ->where('skill', '>', $this->skill)
            ->count() + 1;
    }
    
    // Scopes
    public function scopeByGame($query, string $gameCode)
    {
        return $query->where('game', $gameCode);
    }
    
    public function scopeRanked($query)
    {
        return $query->where('hideranking', 0)
            ->where('kills', '>=', config('hlstats.min_kills', 10))
            ->orderByDesc('skill');
    }
    
    public function scopeActive($query, int $days = 30)
    {
        return $query->where('last_event', '>=', now()->subDays($days));
    }
}
```

#### StatsCalculator Service

```php
<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Event\Kill;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsCalculator
{
    /**
     * Calculate player skill based on Elo-like rating
     */
    public function calculateSkill(Player $player, array $event): float
    {
        $currentSkill = $player->skill ?? 1000;
        
        // Base skill change
        $skillChange = match($event['type']) {
            'kill' => $this->calculateKillSkill($event),
            'action' => $this->calculateActionSkill($event),
            'death' => $this->calculateDeathSkill($event),
            default => 0
        };
        
        // Apply weapon multiplier
        if (isset($event['weapon'])) {
            $weapon = Weapon::where('code', $event['weapon'])->first();
            $skillChange *= ($weapon->modifier ?? 1.0);
        }
        
        // Apply streak bonuses
        if ($player->current_streak >= 5) {
            $skillChange *= 1.2;
        }
        
        return max(0, $currentSkill + $skillChange);
    }
    
    /**
     * Calculate headshot percentage
     */
    public function calculateHeadshotPercentage(Player $player): float
    {
        $stats = Cache::remember(
            "player:{$player->playerId}:headshots",
            now()->addMinutes(5),
            function () use ($player) {
                return DB::table('hlstats_Events_Kills')
                    ->where('killerId', $player->playerId)
                    ->selectRaw('
                        COUNT(*) as total_kills,
                        SUM(CASE WHEN headshot = 1 THEN 1 ELSE 0 END) as headshots
                    ')
                    ->first();
            }
        );
        
        return $stats->total_kills > 0 
            ? round(($stats->headshots / $stats->total_kills) * 100, 2)
            : 0;
    }
    
    /**
     * Get player performance trend
     */
    public function getPerformanceTrend(Player $player, int $days = 7): array
    {
        return DB::table('hlstats_Events_Kills')
            ->where('killerId', $player->playerId)
            ->where('date', '>=', now()->subDays($days))
            ->selectRaw('DATE(eventTime) as date, COUNT(*) as kills')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }
    
    private function calculateKillSkill(array $event): float
    {
        // Base points for kill
        $points = 5;
        
        // Bonus for headshot
        if ($event['headshot'] ?? false) {
            $points += 2;
        }
        
        // Distance bonus
        if (isset($event['distance']) && $event['distance'] > 1000) {
            $points += 3;
        }
        
        return $points;
    }
    
    private function calculateActionSkill(array $event): float
    {
        $action = Action::where('code', $event['action_code'])->first();
        return $action->reward_player ?? 0;
    }
    
    private function calculateDeathSkill(array $event): float
    {
        // Penalty for death
        return -3;
    }
}
```

### 3.3 API Endpoints

#### routes/api.php

```php
<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

// Public API
Route::prefix('v1')->group(function () {
    
    // Games
    Route::get('/games', [Api\GameController::class, 'index']);
    Route::get('/games/{game}', [Api\GameController::class, 'show']);
    
    // Players
    Route::get('/players', [Api\PlayerController::class, 'index']);
    Route::get('/players/{player}', [Api\PlayerController::class, 'show']);
    Route::get('/players/{player}/stats', [Api\PlayerController::class, 'stats']);
    Route::get('/players/{player}/weapons', [Api\PlayerController::class, 'weapons']);
    Route::get('/players/{player}/maps', [Api\PlayerController::class, 'mapPerformance']);
    Route::get('/players/{player}/history', [Api\PlayerController::class, 'history']);
    Route::get('/players/{player}/awards', [Api\PlayerController::class, 'awards']);
    Route::get('/players/search/{term}', [Api\PlayerController::class, 'search']);
    
    // Rankings
    Route::get('/rankings/{game}', [Api\RankingController::class, 'index']);
    Route::get('/rankings/{game}/top/{limit}', [Api\RankingController::class, 'top']);
    
    // Servers
    Route::get('/servers', [Api\ServerController::class, 'index']);
    Route::get('/servers/{server}', [Api\ServerController::class, 'show']);
    Route::get('/servers/{server}/players', [Api\ServerController::class, 'currentPlayers']);
    
    // Clans
    Route::get('/clans/{game}', [Api\ClanController::class, 'index']);
    Route::get('/clans/{clan}', [Api\ClanController::class, 'show']);
    
    // Weapons
    Route::get('/weapons/{game}', [Api\WeaponController::class, 'index']);
    Route::get('/weapons/{game}/{weapon}', [Api\WeaponController::class, 'show']);
    
    // Maps
    Route::get('/maps/{game}', [Api\MapController::class, 'index']);
    Route::get('/maps/{game}/{map}', [Api\MapController::class, 'show']);
    
    // Awards
    Route::get('/awards/{game}', [Api\AwardController::class, 'index']);
    Route::get('/awards/daily', [Api\AwardController::class, 'daily']);
    Route::get('/awards/global', [Api\AwardController::class, 'global']);
    
    // Live Stats (requires authentication for websocket)
    Route::get('/live/servers', [Api\LiveStatsController::class, 'servers']);
    Route::get('/live/events', [Api\LiveStatsController::class, 'recentEvents']);
    
    // Statistics
    Route::get('/stats/activity', [Api\StatsController::class, 'activityGraph']);
    Route::get('/stats/weapons/{game}', [Api\StatsController::class, 'weaponUsage']);
    Route::get('/stats/maps/{game}', [Api\StatsController::class, 'mapPopularity']);
});

// Admin API (protected)
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('games', Api\Admin\GameController::class);
    Route::apiResource('servers', Api\Admin\ServerController::class);
    Route::apiResource('actions', Api\Admin\ActionController::class);
    Route::apiResource('weapons', Api\Admin\WeaponController::class);
    
    Route::post('/recalculate-rankings', [Api\Admin\MaintenanceController::class, 'recalculateRankings']);
    Route::post('/award-daily', [Api\Admin\MaintenanceController::class, 'awardDaily']);
});
```

### 3.4 Real-time Broadcasting

#### config/broadcasting.php

```php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'host' => env('PUSHER_HOST', 'soketi') ?: null,
            'port' => env('PUSHER_PORT', 6001),
            'scheme' => env('PUSHER_SCHEME', 'http'),
            'encrypted' => true,
            'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
        ],
    ],
],
```

#### app/Events/PlayerKilled.php

```php
<?php

namespace App\Events;

use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerKilled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public array $killData,
        public string $serverCode
    ) {}
    
    public function broadcastOn(): array
    {
        return [
            new Channel('live-stats.' . $this->serverCode),
            new Channel('live-stats.all'),
        ];
    }
    
    public function broadcastAs(): string
    {
        return 'player.killed';
    }
    
    public function broadcastWith(): array
    {
        return [
            'killer' => $this->killData['killer'],
            'victim' => $this->killData['victim'],
            'weapon' => $this->killData['weapon'],
            'headshot' => $this->killData['headshot'] ?? false,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

---

## 4. Log Parser Service (Node.js)

### 4.1 Architecture

The Perl daemon needs to be replaced with a modern Node.js service that:
1. Listens for UDP log streams from game servers
2. Parses log events
3. Publishes to Redis/Queue for Laravel to process
4. Optionally broadcasts directly to WebSocket for real-time updates

### 4.2 Implementation Example

#### package.json

```json
{
  "name": "hlstats-log-parser",
  "version": "2.0.0",
  "type": "module",
  "dependencies": {
    "dgram": "^1.0.1",
    "redis": "^4.6.0",
    "bull": "^4.12.0",
    "socket.io": "^4.6.0",
    "winston": "^3.11.0",
    "dotenv": "^16.3.1"
  }
}
```

#### src/parser.js

```javascript
import dgram from 'dgram';
import { Queue } from 'bull';
import Redis from 'redis';
import winston from 'winston';
import { EventParser } from './event-parser.js';

class LogParser {
    constructor(config) {
        this.config = config;
        this.socket = dgram.createSocket('udp4');
        this.redis = Redis.createClient(config.redis);
        this.eventQueue = new Queue('game-events', config.redis);
        
        this.logger = winston.createLogger({
            level: 'info',
            format: winston.format.json(),
            transports: [
                new winston.transports.File({ filename: 'error.log', level: 'error' }),
                new winston.transports.File({ filename: 'combined.log' }),
            ],
        });
        
        this.parser = new EventParser();
        this.setupHandlers();
    }
    
    setupHandlers() {
        this.socket.on('message', async (msg, rinfo) => {
            try {
                const logEntry = msg.toString('utf8');
                const event = this.parser.parse(logEntry, rinfo);
                
                if (event) {
                    await this.processEvent(event);
                }
            } catch (error) {
                this.logger.error('Error processing log:', error);
            }
        });
        
        this.socket.on('error', (err) => {
            this.logger.error(`Socket error: ${err.stack}`);
        });
    }
    
    async processEvent(event) {
        // Add to processing queue
        await this.eventQueue.add(event, {
            attempts: 3,
            backoff: {
                type: 'exponential',
                delay: 1000,
            },
        });
        
        // Publish to Redis pub/sub for real-time updates
        await this.redis.publish(
            `live-events:${event.serverCode}`,
            JSON.stringify(event)
        );
        
        this.logger.info(`Event queued: ${event.type}`, {
            server: event.serverCode,
            type: event.type,
        });
    }
    
    start(port = 27500) {
        this.socket.bind(port, () => {
            this.logger.info(`Log parser listening on UDP port ${port}`);
        });
    }
    
    stop() {
        this.socket.close();
        this.redis.quit();
        this.logger.info('Log parser stopped');
    }
}

export default LogParser;
```

#### src/event-parser.js

```javascript
export class EventParser {
    parse(logLine, remoteInfo) {
        // Remove RL prefix
        const cleanLine = logLine.replace(/^RL\s*/, '');
        
        // Parse timestamp
        const timestampMatch = cleanLine.match(/L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2})/);
        if (!timestampMatch) return null;
        
        const content = cleanLine.substring(timestampMatch[0].length).trim();
        
        // Parse different event types
        if (content.includes(' killed ')) {
            return this.parseKillEvent(content, remoteInfo);
        } else if (content.includes(' connected, address ')) {
            return this.parseConnectEvent(content, remoteInfo);
        } else if (content.includes(' disconnected')) {
            return this.parseDisconnectEvent(content, remoteInfo);
        } else if (content.includes(' triggered "')) {
            return this.parseActionEvent(content, remoteInfo);
        } else if (content.includes(' say ')) {
            return this.parseChatEvent(content, remoteInfo);
        }
        
        return null;
    }
    
    parseKillEvent(content, remoteInfo) {
        // Example: "Player<2><STEAM_0:1:12345><CT>" killed "Enemy<3><STEAM_0:0:67890><TERRORIST>" with "ak47" (headshot)
        const regex = /"([^"]+)<(\d+)><([^>]+)><([^>]*)>"\skilled\s"([^"]+)<(\d+)><([^>]+)><([^>]*)>"\swith\s"([^"]+)"(\s\(headshot\))?/;
        const match = content.match(regex);
        
        if (!match) return null;
        
        return {
            type: 'kill',
            timestamp: new Date(),
            serverCode: remoteInfo.address,
            killer: {
                name: match[1],
                userId: match[2],
                uniqueId: match[3],
                team: match[4],
            },
            victim: {
                name: match[5],
                userId: match[6],
                uniqueId: match[7],
                team: match[8],
            },
            weapon: match[9],
            headshot: !!match[10],
        };
    }
    
    parseConnectEvent(content, remoteInfo) {
        // "Player<2><STEAM_0:1:12345>" connected, address "192.168.1.1:27005"
        const regex = /"([^"]+)<(\d+)><([^>]+)>"\sconnected,\saddress\s"([^"]+)"/;
        const match = content.match(regex);
        
        if (!match) return null;
        
        return {
            type: 'connect',
            timestamp: new Date(),
            serverCode: remoteInfo.address,
            player: {
                name: match[1],
                userId: match[2],
                uniqueId: match[3],
                address: match[4],
            },
        };
    }
    
    parseActionEvent(content, remoteInfo) {
        // "Player<2><STEAM_0:1:12345><CT>" triggered "Planted_The_Bomb"
        const regex = /"([^"]+)<(\d+)><([^>]+)><([^>]*)>"\striggered\s"([^"]+)"/;
        const match = content.match(regex);
        
        if (!match) return null;
        
        return {
            type: 'action',
            timestamp: new Date(),
            serverCode: remoteInfo.address,
            player: {
                name: match[1],
                userId: match[2],
                uniqueId: match[3],
                team: match[4],
            },
            action: match[5],
        };
    }
    
    // Additional parse methods...
}
```

---

## 5. Vue.js Frontend (SPA)

### 5.1 Technology Stack

- **Vue 3** with Composition API
- **Inertia.js** for seamless Laravel integration
- **TailwindCSS** for styling
- **Chart.js / ApexCharts** for data visualization
- **Laravel Echo** for WebSocket communication
- **Pinia** for state management

### 5.2 Project Structure

```
resources/
└── js/
    ├── app.js
    ├── Pages/
    │   ├── Dashboard.vue
    │   ├── Players/
    │   │   ├── Index.vue
    │   │   ├── Show.vue
    │   │   ├── Stats.vue
    │   │   └── Compare.vue
    │   ├── Rankings/
    │   │   └── Index.vue
    │   ├── Servers/
    │   │   ├── Index.vue
    │   │   └── Show.vue
    │   ├── Weapons/
    │   │   └── Index.vue
    │   ├── Clans/
    │   │   ├── Index.vue
    │   │   └── Show.vue
    │   ├── Awards/
    │   │   ├── Daily.vue
    │   │   └── Global.vue
    │   └── LiveStats/
    │       └── Index.vue
    ├── Components/
    │   ├── PlayerCard.vue
    │   ├── ServerStatus.vue
    │   ├── StatsChart.vue
    │   ├── WeaponGrid.vue
    │   ├── KillFeed.vue
    │   └── RankingTable.vue
    ├── Composables/
    │   ├── usePlayer.js
    │   ├── useStats.js
    │   ├── useLiveEvents.js
    │   └── useWebSocket.js
    └── Stores/
        ├── player.js
        ├── server.js
        └── liveStats.js
```

### 5.3 Key Components

#### PlayerProfile.vue

```vue
<script setup>
import { computed, ref, onMounted } from 'vue';
import { usePlayer } from '@/Composables/usePlayer';
import { useLiveEvents } from '@/Composables/useLiveEvents';
import { Head } from '@inertiajs/vue3';
import StatsChart from '@/Components/StatsChart.vue';
import WeaponGrid from '@/Components/WeaponGrid.vue';

const props = defineProps({
    player: Object,
    stats: Object,
    weapons: Array,
    recentMatches: Array,
});

const { updatePlayerStats } = usePlayer();
const { subscribe } = useLiveEvents();

// Computed properties
const kdRatio = computed(() => {
    return props.stats.deaths > 0 
        ? (props.stats.kills / props.stats.deaths).toFixed(2)
        : props.stats.kills;
});

const accuracy = computed(() => {
    return props.stats.shots > 0
        ? ((props.stats.hits / props.stats.shots) * 100).toFixed(2)
        : 0;
});

const headshotPercentage = computed(() => {
    return props.stats.kills > 0
        ? ((props.stats.headshots / props.stats.kills) * 100).toFixed(2)
        : 0;
});

// Subscribe to live updates for this player
onMounted(() => {
    subscribe(`player.${props.player.playerId}`, (event) => {
        updatePlayerStats(props.player.playerId, event);
    });
});
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Head :title="`${player.lastName} - Player Profile`" />
        
        <!-- Player Header -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <!-- Country Flag -->
                    <img 
                        v-if="player.country"
                        :src="`/images/flags/${player.country.toLowerCase()}.png`" 
                        :alt="player.country"
                        class="w-8 h-6"
                    />
                    
                    <!-- Player Name & Rank -->
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ player.lastName }}
                        </h1>
                        <p class="text-gray-500 dark:text-gray-400">
                            Rank #{{ player.rank }} • {{ player.game }}
                        </p>
                    </div>
                </div>
                
                <!-- Skill Rating -->
                <div class="text-right">
                    <div class="text-4xl font-bold text-blue-600">
                        {{ player.skill }}
                    </div>
                    <div class="text-sm text-gray-500">Skill Points</div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Kills</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ stats.kills.toLocaleString() }}
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Deaths</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ stats.deaths.toLocaleString() }}
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">K/D Ratio</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ kdRatio }}
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Headshot %</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ headshotPercentage }}%
                </div>
            </div>
        </div>
        
        <!-- Performance Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                Performance Trend
            </h2>
            <StatsChart :data="stats.performanceTrend" />
        </div>
        
        <!-- Weapon Statistics -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                Weapon Statistics
            </h2>
            <WeaponGrid :weapons="weapons" />
        </div>
        
        <!-- Recent Matches -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                Recent Matches
            </h2>
            <div class="space-y-3">
                <div 
                    v-for="match in recentMatches" 
                    :key="match.id"
                    class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded"
                >
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ match.server_name }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ match.map }} • {{ match.duration }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ match.kills }}/{{ match.deaths }}
                        </div>
                        <div class="text-sm" :class="{
                            'text-green-600': match.skill_change > 0,
                            'text-red-600': match.skill_change < 0,
                        }">
                            {{ match.skill_change > 0 ? '+' : '' }}{{ match.skill_change }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

#### LiveStats.vue

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useLiveEvents } from '@/Composables/useLiveEvents';
import KillFeed from '@/Components/KillFeed.vue';
import ServerStatus from '@/Components/ServerStatus.vue';

const props = defineProps({
    servers: Array,
});

const killFeed = ref([]);
const selectedServer = ref(null);

const { subscribe, unsubscribe } = useLiveEvents();

onMounted(() => {
    // Subscribe to all servers
    subscribe('live-stats.all', (event) => {
        if (event.type === 'player.killed') {
            killFeed.value.unshift(event);
            // Keep only last 50 kills
            if (killFeed.value.length > 50) {
                killFeed.value.pop();
            }
        }
    });
});

onUnmounted(() => {
    unsubscribe('live-stats.all');
});
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
            Live Statistics
        </h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Server List -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                        Active Servers
                    </h2>
                    <div class="space-y-2">
                        <ServerStatus 
                            v-for="server in servers" 
                            :key="server.serverId"
                            :server="server"
                            @select="selectedServer = server"
                        />
                    </div>
                </div>
            </div>
            
            <!-- Kill Feed -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                        Live Kill Feed
                    </h2>
                    <KillFeed :events="killFeed" />
                </div>
            </div>
        </div>
    </div>
</template>
```

#### useLiveEvents Composable

```javascript
// resources/js/Composables/useLiveEvents.js
import { ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    forceTLS: false,
    disableStats: true,
});

export function useLiveEvents() {
    const channels = ref(new Map());
    
    const subscribe = (channelName, callback) => {
        if (channels.value.has(channelName)) {
            return;
        }
        
        const channel = echo.channel(channelName);
        channel.listen('.player.killed', callback);
        channel.listen('.player.connected', callback);
        channel.listen('.player.action', callback);
        
        channels.value.set(channelName, channel);
    };
    
    const unsubscribe = (channelName) => {
        const channel = channels.value.get(channelName);
        if (channel) {
            channel.stopListening('.player.killed');
            channel.stopListening('.player.connected');
            channel.stopListening('.player.action');
            echo.leaveChannel(channelName);
            channels.value.delete(channelName);
        }
    };
    
    const unsubscribeAll = () => {
        channels.value.forEach((_, channelName) => {
            unsubscribe(channelName);
        });
    };
    
    return {
        subscribe,
        unsubscribe,
        unsubscribeAll,
    };
}
```

---

## 6. Game Server Plugin Compatibility

### 6.1 SourceMod Plugin Updates

The existing SourceMod plugins can continue to function with minimal changes:

1. **UDP Log Format**: Keep the same log format for backward compatibility
2. **New Endpoints**: Add optional HTTP/REST endpoints for bidirectional communication
3. **Configuration**: Update plugin config to point to new parser service

#### Updated Plugin Config

```ini
// addons/sourcemod/configs/hlstatsx.cfg
"HLstatsX"
{
    // Legacy UDP logging (keep for compatibility)
    "udp_log_server" "your-parser-server.com"
    "udp_log_port" "27500"
    
    // New REST API (optional)
    "api_endpoint" "https://api.yourstats.com/v1/events"
    "api_key" "your-secret-key"
    
    // WebSocket for instant feedback (optional)
    "websocket_enabled" "1"
    "websocket_server" "wss://ws.yourstats.com"
}
```

---

## 7. Database Migration Strategy

### 7.1 Schema Modernization

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Players table with improvements
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('game_code', 32)->index();
            $table->decimal('skill', 10, 2)->default(1000);
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('connection_time')->default(0);
            $table->string('country', 2)->nullable();
            $table->string('city', 50)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('hide_ranking')->default(false);
            $table->timestamp('last_event')->nullable();
            $table->timestamps();
            
            $table->index(['game_code', 'skill']);
            $table->index(['game_code', 'kills']);
        });
        
        // Player unique IDs (Steam IDs, etc.)
        Schema::create('player_unique_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('unique_id');
            $table->timestamp('first_seen');
            $table->timestamp('last_seen');
            $table->timestamps();
            
            $table->unique('unique_id');
            $table->index('player_id');
        });
        
        // Events - Kill log
        Schema::create('events_kills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('killer_id')->nullable()->constrained('players');
            $table->foreignId('victim_id')->nullable()->constrained('players');
            $table->foreignId('server_id')->constrained();
            $table->string('weapon_code', 64);
            $table->boolean('headshot')->default(false);
            $table->integer('distance')->nullable();
            $table->json('metadata')->nullable(); // For additional data
            $table->timestamp('event_time')->index();
            $table->timestamps();
            
            $table->index(['killer_id', 'event_time']);
            $table->index(['server_id', 'event_time']);
        });
        
        // Add more tables...
    }
};
```

### 7.2 Data Migration Script

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateHLStatsData extends Command
{
    protected $signature = 'hlstats:migrate {--batch=1000}';
    protected $description = 'Migrate data from old HLstatsX schema to new schema';
    
    public function handle()
    {
        $this->info('Starting HLstatsX data migration...');
        
        // Migrate players
        $this->migratePlayers();
        
        // Migrate events
        $this->migrateEvents();
        
        // Migrate weapons
        $this->migrateWeapons();
        
        $this->info('Migration completed!');
    }
    
    private function migratePlayers()
    {
        $this->info('Migrating players...');
        
        $count = 0;
        DB::table('hlstats_Players')->orderBy('playerId')->chunk($this->option('batch'), function ($oldPlayers) use (&$count) {
            $newPlayers = [];
            
            foreach ($oldPlayers as $oldPlayer) {
                $newPlayers[] = [
                    'id' => $oldPlayer->playerId,
                    'last_name' => $oldPlayer->lastName,
                    'game_code' => $oldPlayer->game,
                    'skill' => $oldPlayer->skill,
                    'kills' => $oldPlayer->kills,
                    'deaths' => $oldPlayer->deaths,
                    'connection_time' => $oldPlayer->connection_time,
                    'country' => $oldPlayer->country,
                    'city' => $oldPlayer->city,
                    'hide_ranking' => $oldPlayer->hideranking == '1',
                    'last_event' => $oldPlayer->last_event,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DB::table('players')->insert($newPlayers);
            $count += count($newPlayers);
            $this->info("Migrated {$count} players...");
        });
        
        $this->info("Migrated {$count} players total.");
    }
    
    // Additional migration methods...
}
```

---

## 8. Performance Optimizations

### 8.1 Caching Strategy

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheStrategy
{
    /**
     * Cache player rankings with tags
     */
    public function cacheRankings(string $gameCode, int $page = 1)
    {
        $cacheKey = "rankings:{$gameCode}:page:{$page}";
        
        return Cache::tags(['rankings', "game:{$gameCode}"])
            ->remember($cacheKey, now()->addMinutes(5), function () use ($gameCode, $page) {
                return Player::byGame($gameCode)
                    ->ranked()
                    ->paginate(50, ['*'], 'page', $page);
            });
    }
    
    /**
     * Invalidate caches on player update
     */
    public function invalidatePlayerCache(int $playerId, string $gameCode)
    {
        Cache::tags(['rankings', "game:{$gameCode}"])->flush();
        Cache::forget("player:{$playerId}:stats");
        Cache::forget("player:{$playerId}:weapons");
    }
    
    /**
     * Use Redis for real-time leaderboard
     */
    public function updateLiveLeaderboard(string $gameCode, int $playerId, float $skill)
    {
        Redis::zadd("leaderboard:{$gameCode}", $skill, $playerId);
    }
    
    public function getLiveLeaderboard(string $gameCode, int $limit = 100)
    {
        $playerIds = Redis::zrevrange("leaderboard:{$gameCode}", 0, $limit - 1, true);
        
        // Fetch player details
        return Player::whereIn('id', array_keys($playerIds))
            ->get()
            ->sortByDesc(function ($player) use ($playerIds) {
                return $playerIds[$player->id];
            });
    }
}
```

### 8.2 Database Indexing

```sql
-- Add composite indexes for common queries
CREATE INDEX idx_kills_killer_time ON events_kills(killer_id, event_time DESC);
CREATE INDEX idx_kills_server_time ON events_kills(server_id, event_time DESC);
CREATE INDEX idx_players_game_skill ON players(game_code, skill DESC, hide_ranking);

-- Add covering index for rankings query
CREATE INDEX idx_players_ranking_cover ON players(game_code, hide_ranking, skill DESC) 
INCLUDE (id, last_name, kills, deaths, country);

-- Partition events_kills by date for better performance
ALTER TABLE events_kills PARTITION BY RANGE (YEAR(event_time)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### 8.3 Queue Configuration

```php
// config/queue.php

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],

'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],

// Separate queues for different priorities
'queues' => [
    'critical' => ['events', 'live-updates'],
    'high' => ['rankings', 'awards'],
    'default' => ['notifications', 'emails'],
    'low' => ['cleanup', 'maintenance'],
],
```

---

## 9. Deployment & Infrastructure

### 9.1 Docker Compose Setup

```yaml
version: '3.8'

services:
  # Laravel Application
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./:/var/www/html
    environment:
      - APP_ENV=production
      - DB_HOST=database
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
    depends_on:
      - database
      - redis
  
  # Web Server
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx:/etc/nginx/conf.d
    depends_on:
      - app
  
  # Database
  database:
    image: mysql:8.0
    environment:
      - MYSQL_DATABASE=hlstats
      - MYSQL_ROOT_PASSWORD=${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"
  
  # Redis (Cache & Queue)
  redis:
    image: redis:alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
  
  # Queue Worker
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan queue:work --tries=3
    volumes:
      - ./:/var/www/html
    depends_on:
      - database
      - redis
  
  # Log Parser Service (Node.js)
  log-parser:
    build:
      context: ./log-parser
      dockerfile: Dockerfile
    ports:
      - "27500:27500/udp"
    environment:
      - REDIS_URL=redis://redis:6379
    depends_on:
      - redis
  
  # WebSocket Server (Soketi)
  soketi:
    image: 'quay.io/soketi/soketi:latest-16-alpine'
    environment:
      - SOKETI_DEFAULT_APP_ID=${PUSHER_APP_ID}
      - SOKETI_DEFAULT_APP_KEY=${PUSHER_APP_KEY}
      - SOKETI_DEFAULT_APP_SECRET=${PUSHER_APP_SECRET}
    ports:
      - "6001:6001"

volumes:
  db_data:
  redis_data:
```

### 9.2 Production Checklist

- [ ] Enable Laravel optimization
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  ```

- [ ] Set up queue workers with Supervisor
  ```ini
  [program:hlstats-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/html/artisan queue:work redis --tries=3
  autostart=true
  autorestart=true
  numprocs=4
  user=www-data
  ```

- [ ] Configure CDN for static assets
- [ ] Set up database replication for read scaling
- [ ] Implement rate limiting on API endpoints
- [ ] Set up monitoring (Laravel Telescope, Sentry)
- [ ] Configure automated backups
- [ ] Enable HTTPS with Let's Encrypt
- [ ] Set up log rotation
- [ ] Configure firewall rules (UDP 27500 for game servers)

---

## 10. Feature Parity Checklist

### Core Features
- ✅ Real-time log parsing
- ✅ Player statistics and rankings
- ✅ Weapon statistics
- ✅ Map performance tracking
- ✅ Clan rankings
- ✅ Daily/Global awards
- ✅ Kill/Death tracking with context
- ✅ Headshot tracking
- ✅ Action events (bomb plants, captures, etc.)
- ✅ Server monitoring
- ✅ GeoIP location tracking
- ✅ Player aliases
- ✅ Session tracking
- ✅ Live kill feed

### Enhanced Features (New)
- 🆕 Real-time WebSocket updates
- 🆕 REST API for third-party integrations
- 🆕 Modern responsive UI
- 🆕 Dark mode support
- 🆕 Advanced filtering and search
- 🆕 Performance graphs and charts
- 🆕 Player comparison tools
- 🆕 Mobile-optimized interface
- 🆕 OAuth authentication
- 🆕 Social features (profiles, comments)
- 🆕 Export data (CSV, JSON)
- 🆕 Webhooks for events

### Admin Features
- ✅ Game configuration management
- ✅ Server management
- ✅ Action/Award management
- ✅ Weapon configuration
- ✅ Ban management
- ✅ Manual ranking recalculation
- 🆕 Dashboard with analytics
- 🆕 Audit logs
- 🆕 Role-based access control

---

## 11. Migration Timeline

### Phase 1: Foundation (Weeks 1-4)
- Set up Laravel project structure
- Design and implement database schema
- Create core models and relationships
- Build basic API endpoints
- Set up authentication

### Phase 2: Log Parser (Weeks 5-6)
- Develop Node.js log parser service
- Implement event parsing logic
- Set up Redis queue integration
- Test with live game servers

### Phase 3: Vue Frontend (Weeks 7-10)
- Set up Vue 3 with Inertia.js
- Implement core pages (players, rankings, servers)
- Build reusable components
- Integrate with Laravel API
- Implement real-time updates

### Phase 4: Advanced Features (Weeks 11-12)
- Implement awards system
- Build admin panel
- Add charts and visualization
- Optimize performance
- Add caching layers

### Phase 5: Testing & Deployment (Weeks 13-14)
- Load testing
- Security audit
- Bug fixes
- Documentation
- Production deployment

---

## 12. Maintenance & Scalability

### Horizontal Scaling Strategy

```
Load Balancer (Nginx/HAProxy)
        │
        ├─── App Server 1 (Laravel)
        ├─── App Server 2 (Laravel)
        └─── App Server 3 (Laravel)
                │
        ┌───────┴────────┐
        │                │
   DB Primary      Redis Cluster
        │                │
   DB Replicas    Queue Workers (separate)
```

### Monitoring Setup

```php
// config/telescope.php (for development/staging)
// Use Laravel Pulse for production monitoring

// Custom metrics
Log::channel('metrics')->info('player.ranked', [
    'player_id' => $player->id,
    'rank' => $player->rank,
    'skill' => $player->skill,
]);

// Performance monitoring
DB::listen(function ($query) {
    if ($query->time > 100) {
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
        ]);
    }
});
```

---

## 13. Security Considerations

### API Security

```php
// Rate limiting
Route::middleware('throttle:api')->group(function () {
    // Public API limited to 60 requests/minute
});

// Authentication for sensitive endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/rankings/recalculate', ...);
});

// CORS configuration
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST'],
'allowed_origins' => [
    'https://yourdomain.com',
],
```

### Input Validation

```php
// All user inputs validated
public function store(Request $request)
{
    $validated = $request->validate([
        'player_name' => 'required|string|max:64|regex:/^[a-zA-Z0-9_\-]+$/',
        'unique_id' => 'required|string|regex:/^STEAM_[0-9]:[01]:\d+$/',
    ]);
    
    // Process validated data...
}
```

### SQL Injection Prevention

```php
// Always use Eloquent ORM or Query Builder
Player::where('lastName', $name)->get();  // ✅ Safe

// Never use raw queries with user input
DB::select("SELECT * FROM players WHERE name = '$name'");  // ❌ Vulnerable
```

---

## Conclusion

This modernization plan transforms HLstatsX from a legacy Perl/PHP application into a modern, scalable, real-time statistics platform. Key benefits include:

1. **Performance**: 10x faster page loads with Vue SPA and caching
2. **Real-time**: WebSocket-based live updates
3. **Scalability**: Horizontal scaling with queue workers
4. **Maintainability**: Modern codebase with Laravel 11 and Vue 3
5. **Developer Experience**: Type-safe, well-documented, testable code
6. **User Experience**: Responsive, intuitive interface with dark mode

The phased approach ensures backward compatibility with existing game servers while enabling gradual migration and testing.

---

## Resources

- [Laravel Documentation](https://laravel.com/docs/11.x)
- [Vue 3 Documentation](https://vuejs.org/)
- [Inertia.js Documentation](https://inertiajs.com/)
- [SourceMod Scripting](https://wiki.alliedmods.net/Introduction_to_SourcePawn)
- [Source Engine Logging](https://developer.valvesoftware.com/wiki/HL_Log_Standard)
- [Original HLstatsX](https://github.com/NomisCZ/hlstatsx-community-edition)
