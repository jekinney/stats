# HLstatsX Modernization: TDD Implementation Plan (Red-to-Green)

## Overview

This plan follows strict Test-Driven Development principles:
1. **RED** - Write failing test first
2. **GREEN** - Write minimal code to pass test
3. **REFACTOR** - Improve code while keeping tests green

**Timeline:** 14 weeks  
**Approach:** Test-first for all features  
**Test Framework:** Pest 4 (already installed)  
**Coverage Target:** 80%+ code coverage  

---

## Phase 1: Database Foundation (Weeks 1-2)

### Week 1: Database Schema Migration & Optimization

#### Day 1-2: Storage Engine Migration Tests

**RED - Write Test First:**
```php
// tests/Feature/Database/StorageEngineTest.php
<?php

use Illuminate\Support\Facades\DB;

test('all tables use InnoDB storage engine', function () {
    $myIsamTables = DB::select("
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND ENGINE = 'MyISAM'
    ");
    
    expect($myIsamTables)->toBeEmpty();
});

test('tables have proper character set', function () {
    $tables = DB::select("
        SELECT TABLE_NAME, TABLE_COLLATION 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_COLLATION != 'utf8mb4_unicode_ci'
    ");
    
    expect($tables)->toBeEmpty();
});
```

**GREEN - Create Migration:**
```bash
php artisan make:migration convert_tables_to_innodb
```

```php
// database/migrations/xxxx_convert_tables_to_innodb.php
public function up(): void
{
    $tables = ['hlstats_players', 'hlstats_events_frags', ...];
    
    foreach ($tables as $table) {
        DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
    }
}
```

**Run Test → Should Pass:**
```bash
php artisan test --filter=StorageEngineTest
```

---

#### Day 3-4: Index Optimization Tests

**RED - Write Test First:**
```php
// tests/Feature/Database/IndexOptimizationTest.php
<?php

use Illuminate\Support\Facades\DB;

test('geoip blocks table has ip range index', function () {
    $indexes = DB::select("
        SHOW INDEX FROM hlstats_geoip_blocks 
        WHERE Key_name = 'idx_ip_range'
    ");
    
    expect($indexes)->not->toBeEmpty();
});

test('events_frags table has killer_time composite index', function () {
    $indexes = DB::select("
        SHOW INDEX FROM hlstats_events_frags 
        WHERE Key_name = 'idx_killer_time'
    ");
    
    expect($indexes)->not->toBeEmpty()
        ->and($indexes)->toHaveCount(2); // killerId + eventTime
});

test('players table has skill ranking index', function () {
    $indexes = DB::select("
        SHOW INDEX FROM hlstats_players 
        WHERE Key_name = 'idx_skill_ranking'
    ");
    
    expect($indexes)->not->toBeEmpty();
});

// Test index usage with EXPLAIN
test('player ranking query uses skill index', function () {
    $explain = DB::select("
        EXPLAIN SELECT * FROM hlstats_players 
        WHERE game = 'csgo' 
        ORDER BY skill DESC 
        LIMIT 100
    ");
    
    expect($explain[0]->key)->toBe('idx_game_skill');
});
```

**GREEN - Create Migration:**
```bash
php artisan make:migration add_performance_indexes
```

```php
// database/migrations/xxxx_add_performance_indexes.php
public function up(): void
{
    Schema::table('hlstats_geoip_blocks', function (Blueprint $table) {
        $table->index(['start_ip_num', 'end_ip_num', 'location_id'], 'idx_ip_range');
    });

    Schema::table('hlstats_events_frags', function (Blueprint $table) {
        $table->index(['killer_id', 'event_time'], 'idx_killer_time');
        $table->index(['victim_id', 'event_time'], 'idx_victim_time');
    });

    Schema::table('hlstats_players', function (Blueprint $table) {
        $table->index(['game', 'skill', 'hide_ranking'], 'idx_game_skill');
    });
}
```

---

#### Day 5: Foreign Key Constraints Tests

**RED - Write Test First:**
```php
// tests/Feature/Database/ForeignKeyTest.php
<?php

use App\Models\Player;
use App\Models\Game;
use App\Models\EventFrag;
use Illuminate\Support\Facades\DB;

test('players table has foreign key to games', function () {
    $constraints = DB::select("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hlstats_players' 
        AND REFERENCED_TABLE_NAME = 'hlstats_games'
    ");
    
    expect($constraints)->not->toBeEmpty();
});

test('cannot create player with invalid game code', function () {
    Player::factory()->create(['game' => 'invalid_game']);
})->throws(\Illuminate\Database\QueryException::class);

test('deleting game cascades to players', function () {
    $game = Game::factory()->create(['code' => 'testgame']);
    $player = Player::factory()->create(['game' => 'testgame']);
    
    $game->delete();
    
    expect(Player::find($player->id))->toBeNull();
});

test('events_frags has foreign key to players', function () {
    $constraints = DB::select("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hlstats_events_frags' 
        AND REFERENCED_TABLE_NAME = 'hlstats_players'
    ");
    
    expect($constraints)->toHaveCount(2); // killer_id and victim_id
});
```

**GREEN - Create Migration:**
```bash
php artisan make:migration add_foreign_key_constraints
```

---

### Week 2: Core Models with Factories

#### Day 1-2: Player Model TDD

**RED - Write Test First:**
```php
// tests/Unit/Models/PlayerTest.php
<?php

use App\Models\Player;
use App\Models\Game;

test('player belongs to game', function () {
    $game = Game::factory()->create();
    $player = Player::factory()->create(['game' => $game->code]);
    
    expect($player->game)->toBeInstanceOf(Game::class)
        ->and($player->game->code)->toBe($game->code);
});

test('player has many kills', function () {
    $player = Player::factory()->create();
    
    expect($player->kills())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('player has many deaths', function () {
    $player = Player::factory()->create();
    
    expect($player->deaths())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('player calculates kd ratio', function () {
    $player = Player::factory()->create([
        'kills' => 100,
        'deaths' => 50,
    ]);
    
    expect($player->kd_ratio)->toBe(2.0);
});

test('player kd ratio handles zero deaths', function () {
    $player = Player::factory()->create([
        'kills' => 100,
        'deaths' => 0,
    ]);
    
    expect($player->kd_ratio)->toBe(100.0);
});

test('player has skill points', function () {
    $player = Player::factory()->create([
        'skill' => 1500.50,
    ]);
    
    expect($player->skill)->toBe(1500.50);
});

test('player can be hidden from rankings', function () {
    $player = Player::factory()->create([
        'hide_ranking' => true,
    ]);
    
    expect($player->hide_ranking)->toBeTrue();
});

// Scopes
test('active players scope excludes hidden', function () {
    Player::factory()->count(3)->create(['hide_ranking' => false]);
    Player::factory()->count(2)->create(['hide_ranking' => true]);
    
    $activePlayers = Player::active()->get();
    
    expect($activePlayers)->toHaveCount(3);
});

test('by game scope filters correctly', function () {
    Player::factory()->count(3)->create(['game' => 'csgo']);
    Player::factory()->count(2)->create(['game' => 'tf2']);
    
    $csgoPlayers = Player::byGame('csgo')->get();
    
    expect($csgoPlayers)->toHaveCount(3);
});

test('top ranked scope orders by skill', function () {
    Player::factory()->create(['skill' => 1000]);
    $topPlayer = Player::factory()->create(['skill' => 2000]);
    Player::factory()->create(['skill' => 1500]);
    
    $top = Player::topRanked()->first();
    
    expect($top->id)->toBe($topPlayer->id);
});
```

**GREEN - Create Model:**
```bash
php artisan make:model Player
php artisan make:factory PlayerFactory
```

```php
// app/Models/Player.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $table = 'hlstats_players';
    protected $primaryKey = 'player_id';

    protected $fillable = [
        'game',
        'last_name',
        'skill',
        'kills',
        'deaths',
        'hide_ranking',
    ];

    protected $casts = [
        'skill' => 'float',
        'kills' => 'integer',
        'deaths' => 'integer',
        'hide_ranking' => 'boolean',
    ];

    // Relationships
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game', 'code');
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
        return $query->where('game', $game);
    }

    public function scopeTopRanked($query)
    {
        return $query->orderBy('skill', 'desc');
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=PlayerTest
```

---

#### Day 3: EventFrag Model TDD

**RED - Write Test First:**
```php
// tests/Unit/Models/EventFragTest.php
<?php

use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;

test('event frag belongs to killer', function () {
    $killer = Player::factory()->create();
    $event = EventFrag::factory()->create(['killer_id' => $killer->id]);
    
    expect($event->killer)->toBeInstanceOf(Player::class)
        ->and($event->killer->id)->toBe($killer->id);
});

test('event frag belongs to victim', function () {
    $victim = Player::factory()->create();
    $event = EventFrag::factory()->create(['victim_id' => $victim->id]);
    
    expect($event->victim)->toBeInstanceOf(Player::class);
});

test('event frag belongs to server', function () {
    $server = Server::factory()->create();
    $event = EventFrag::factory()->create(['server_id' => $server->id]);
    
    expect($event->server)->toBeInstanceOf(Server::class);
});

test('event frag belongs to weapon', function () {
    $weapon = Weapon::factory()->create();
    $event = EventFrag::factory()->create(['weapon' => $weapon->code]);
    
    expect($event->weapon)->toBeInstanceOf(Weapon::class);
});

test('event frag has headshot flag', function () {
    $event = EventFrag::factory()->create(['headshot' => true]);
    
    expect($event->headshot)->toBeTrue();
});

test('event frag has position coordinates', function () {
    $event = EventFrag::factory()->create([
        'pos_x' => 100,
        'pos_y' => 200,
        'pos_z' => 50,
    ]);
    
    expect($event->pos_x)->toBe(100)
        ->and($event->pos_y)->toBe(200)
        ->and($event->pos_z)->toBe(50);
});

// Scopes
test('recent scope filters by date', function () {
    EventFrag::factory()->create(['event_time' => now()->subDays(10)]);
    EventFrag::factory()->create(['event_time' => now()->subHours(1)]);
    
    $recent = EventFrag::recent(7)->get();
    
    expect($recent)->toHaveCount(1);
});

test('headshots only scope', function () {
    EventFrag::factory()->count(3)->create(['headshot' => true]);
    EventFrag::factory()->count(2)->create(['headshot' => false]);
    
    $headshots = EventFrag::headshotsOnly()->get();
    
    expect($headshots)->toHaveCount(3);
});

test('by map scope', function () {
    EventFrag::factory()->count(3)->create(['map' => 'de_dust2']);
    EventFrag::factory()->count(2)->create(['map' => 'de_inferno']);
    
    $dust2Kills = EventFrag::byMap('de_dust2')->get();
    
    expect($dust2Kills)->toHaveCount(3);
});
```

**GREEN - Create Model:**
```bash
php artisan make:model EventFrag
php artisan make:factory EventFragFactory
```

---

#### Day 4-5: Remaining Core Models

Create tests and models for:
- `Game` (with validation tests)
- `Server` (with online/offline status tests)
- `Weapon` (with kill modifier tests)
- `Award` (with tier system tests)
- `Session` (with duration calculation tests)

---

## Phase 2: API Endpoints (Weeks 3-5)

### Week 3: Player API

#### Day 1-2: Player Rankings Endpoint

**RED - Write Test First:**
```php
// tests/Feature/Api/PlayerRankingsTest.php
<?php

use App\Models\Player;
use App\Models\Game;

test('can get player rankings', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(10)->create(['game' => 'csgo']);
    
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'skill',
                    'kills',
                    'deaths',
                    'kd_ratio',
                    'rank',
                ]
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
            ]
        ]);
});

test('rankings are ordered by skill descending', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->create(['game' => 'csgo', 'skill' => 1000]);
    Player::factory()->create(['game' => 'csgo', 'skill' => 2000]);
    Player::factory()->create(['game' => 'csgo', 'skill' => 1500]);
    
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    $players = $response->json('data');
    
    expect($players[0]['skill'])->toBe(2000.0)
        ->and($players[1]['skill'])->toBe(1500.0)
        ->and($players[2]['skill'])->toBe(1000.0);
});

test('rankings exclude hidden players', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(3)->create(['game' => 'csgo', 'hide_ranking' => false]);
    Player::factory()->count(2)->create(['game' => 'csgo', 'hide_ranking' => true]);
    
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    expect($response->json('meta.total'))->toBe(3);
});

test('rankings pagination works', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(50)->create(['game' => 'csgo']);
    
    $response = $this->getJson('/api/players/rankings?game=csgo&per_page=10&page=2');
    
    $response->assertOk();
    
    expect($response->json('meta.current_page'))->toBe(2)
        ->and($response->json('meta.per_page'))->toBe(10);
});

test('rankings require game parameter', function () {
    $response = $this->getJson('/api/players/rankings');
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('rankings validate game exists', function () {
    $response = $this->getJson('/api/players/rankings?game=invalid');
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['game']);
});

test('rankings are cached for 5 minutes', function () {
    Game::factory()->create(['code' => 'csgo']);
    Player::factory()->count(5)->create(['game' => 'csgo']);
    
    // First request
    $this->getJson('/api/players/rankings?game=csgo');
    
    // Add more players
    Player::factory()->count(5)->create(['game' => 'csgo']);
    
    // Second request should return cached data
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    expect($response->json('meta.total'))->toBe(5); // Not 10
});
```

**GREEN - Create Controller:**
```bash
php artisan make:controller Api/PlayerController --api
php artisan make:request PlayerRankingRequest
php artisan make:resource PlayerResource
```

```php
// app/Http/Controllers/Api/PlayerController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRankingRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class PlayerController extends Controller
{
    public function rankings(PlayerRankingRequest $request)
    {
        $validated = $request->validated();
        
        $cacheKey = "rankings:{$validated['game']}:{$request->get('page', 1)}";
        
        $players = Cache::remember($cacheKey, 300, function () use ($validated, $request) {
            return Player::query()
                ->byGame($validated['game'])
                ->active()
                ->topRanked()
                ->paginate($request->get('per_page', 20));
        });
        
        return PlayerResource::collection($players);
    }
}
```

```php
// app/Http/Requests/PlayerRankingRequest.php
<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class PlayerRankingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game' => ['required', 'string', 'exists:hlstats_games,code'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
```

```php
// app/Http/Resources/PlayerResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->player_id,
            'name' => $this->last_name,
            'skill' => $this->skill,
            'kills' => $this->kills,
            'deaths' => $this->deaths,
            'kd_ratio' => $this->kd_ratio,
            'rank' => $this->when($this->rank, fn () => $this->rank),
            'last_event' => $this->last_event?->toIso8601String(),
        ];
    }
}
```

**Add Route:**
```php
// routes/api.php
Route::get('players/rankings', [PlayerController::class, 'rankings']);
```

**Run Tests:**
```bash
php artisan test --filter=PlayerRankingsTest
```

---

#### Day 3-4: Player Profile Endpoint

**RED - Write Test First:**
```php
// tests/Feature/Api/PlayerProfileTest.php
<?php

use App\Models\Player;
use App\Models\EventFrag;
use App\Models\Weapon;

test('can get player profile', function () {
    $player = Player::factory()->create();
    
    $response = $this->getJson("/api/players/{$player->id}");
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'skill',
                'kills',
                'deaths',
                'kd_ratio',
                'headshots',
                'accuracy',
                'favorite_weapon',
                'recent_matches',
                'statistics',
            ]
        ]);
});

test('player profile includes recent kills', function () {
    $player = Player::factory()->create();
    EventFrag::factory()->count(5)->create(['killer_id' => $player->id]);
    
    $response = $this->getJson("/api/players/{$player->id}");
    
    expect($response->json('data.recent_kills'))->toHaveCount(5);
});

test('player profile includes weapon statistics', function () {
    $player = Player::factory()->create();
    $weapon = Weapon::factory()->create(['code' => 'ak47']);
    
    EventFrag::factory()->count(10)->create([
        'killer_id' => $player->id,
        'weapon' => 'ak47',
    ]);
    
    $response = $this->getJson("/api/players/{$player->id}");
    
    $weapons = $response->json('data.weapon_stats');
    
    expect($weapons)->toHaveCount(1)
        ->and($weapons[0]['weapon'])->toBe('ak47')
        ->and($weapons[0]['kills'])->toBe(10);
});

test('player profile returns 404 for non-existent player', function () {
    $response = $this->getJson('/api/players/99999');
    
    $response->assertNotFound();
});

test('player profile calculates headshot percentage', function () {
    $player = Player::factory()->create();
    
    EventFrag::factory()->count(7)->create([
        'killer_id' => $player->id,
        'headshot' => true,
    ]);
    
    EventFrag::factory()->count(3)->create([
        'killer_id' => $player->id,
        'headshot' => false,
    ]);
    
    $response = $this->getJson("/api/players/{$player->id}");
    
    expect($response->json('data.headshot_percentage'))->toBe(70.0);
});
```

**GREEN - Implement:**
```php
// Add to PlayerController
public function show(Player $player)
{
    $player->loadCount([
        'kills',
        'deaths',
        'kills as headshot_count' => fn($q) => $q->where('headshot', true),
    ]);
    
    $player->load([
        'kills' => fn($q) => $q->latest()->limit(10),
        'weaponStats',
    ]);
    
    return new PlayerResource($player);
}
```

---

#### Day 5: Player Search Endpoint

**RED - Write Test First:**
```php
// tests/Feature/Api/PlayerSearchTest.php
<?php

use App\Models\Player;

test('can search players by name', function () {
    Player::factory()->create(['last_name' => 'ProGamer123']);
    Player::factory()->create(['last_name' => 'NoobMaster']);
    Player::factory()->create(['last_name' => 'ProPlayer456']);
    
    $response = $this->getJson('/api/players/search?q=Pro');
    
    $response->assertOk();
    
    expect($response->json('data'))->toHaveCount(2);
});

test('search is case insensitive', function () {
    Player::factory()->create(['last_name' => 'ProGamer']);
    
    $response = $this->getJson('/api/players/search?q=progamer');
    
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('search requires minimum 3 characters', function () {
    $response = $this->getJson('/api/players/search?q=ab');
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('search can be filtered by game', function () {
    Player::factory()->create(['last_name' => 'Player1', 'game' => 'csgo']);
    Player::factory()->create(['last_name' => 'Player2', 'game' => 'tf2']);
    
    $response = $this->getJson('/api/players/search?q=Player&game=csgo');
    
    expect($response->json('data'))->toHaveCount(1);
});
```

**GREEN - Implement search endpoint**

---

### Week 4: Weapon & Map Statistics API

#### Similar TDD approach for:
- Weapon rankings endpoint
- Map statistics endpoint
- Server status endpoint
- Live kill feed endpoint

---

### Week 5: Real-Time Statistics

#### Day 1-3: WebSocket Events

**RED - Write Test First:**
```php
// tests/Feature/Realtime/KillFeedBroadcastTest.php
<?php

use App\Events\KillFeedEvent;
use App\Models\EventFrag;
use Illuminate\Support\Facades\Event;

test('kill event is broadcast when frag is created', function () {
    Event::fake([KillFeedEvent::class]);
    
    $frag = EventFrag::factory()->create();
    
    event(new KillFeedEvent($frag));
    
    Event::assertDispatched(KillFeedEvent::class);
});

test('kill event contains killer and victim data', function () {
    $frag = EventFrag::factory()->create();
    
    $event = new KillFeedEvent($frag);
    
    expect($event->frag->killer_id)->not->toBeNull()
        ->and($event->frag->victim_id)->not->toBeNull();
});

test('kill event broadcasts on game-specific channel', function () {
    $frag = EventFrag::factory()->create();
    
    $event = new KillFeedEvent($frag);
    
    expect($event->broadcastOn()[0]->name)->toBe("game.{$frag->game}");
});
```

**GREEN - Create Event:**
```bash
php artisan make:event KillFeedEvent
```

```php
// app/Events/KillFeedEvent.php
<?php

namespace App\Events;

use App\Models\EventFrag;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KillFeedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EventFrag $frag)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->frag->game}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kill.feed';
    }

    public function broadcastWith(): array
    {
        return [
            'killer' => [
                'id' => $this->frag->killer_id,
                'name' => $this->frag->killer->last_name,
            ],
            'victim' => [
                'id' => $this->frag->victim_id,
                'name' => $this->frag->victim->last_name,
            ],
            'weapon' => $this->frag->weapon,
            'headshot' => $this->frag->headshot,
            'timestamp' => $this->frag->event_time,
        ];
    }
}
```

---

## Phase 3: Log Parser Service (Weeks 6-7)

### Week 6: UDP Log Receiver

#### Day 1-2: Log Packet Parser Tests

**RED - Write Test First:**
```php
// tests/Unit/Services/LogParserTest.php
<?php

use App\Services\LogParser;

test('can parse player kill event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" killed "Player2<456><STEAM_1:0:67890><TERRORIST>" with "ak47" (headshot)';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('kill')
        ->and($event['killer']['name'])->toBe('Player1')
        ->and($event['killer']['steam_id'])->toBe('STEAM_1:0:12345')
        ->and($event['victim']['name'])->toBe('Player2')
        ->and($event['weapon'])->toBe('ak47')
        ->and($event['headshot'])->toBeTrue();
});

test('can parse player connect event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><>" connected, address "192.168.1.1:27005"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('connect')
        ->and($event['player']['name'])->toBe('Player1')
        ->and($event['player']['steam_id'])->toBe('STEAM_1:0:12345')
        ->and($event['ip_address'])->toBe('192.168.1.1');
});

test('can parse player disconnect event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" disconnected (reason "Disconnect by user.")';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('disconnect')
        ->and($event['player']['name'])->toBe('Player1')
        ->and($event['reason'])->toContain('user');
});

test('can parse player chat event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" say "gg wp"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('chat')
        ->and($event['message'])->toBe('gg wp');
});

test('can parse team chat event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" say_team "rush B"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('team_chat')
        ->and($event['message'])->toBe('rush B');
});

test('can parse map change event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: Loading map "de_dust2"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('map_change')
        ->and($event['map'])->toBe('de_dust2');
});

test('can parse round end event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: World triggered "Round_End"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['type'])->toBe('round_end');
});

test('parser handles malformed log lines gracefully', function () {
    $logLine = 'Invalid log format';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event)->toBeNull();
});

test('parser extracts position coordinates from kill event', function () {
    $logLine = 'L 02/09/2026 - 12:34:56: "Player1<123><STEAM_1:0:12345><CT>" [-1234 2345 64] killed "Player2<456><STEAM_1:0:67890><TERRORIST>" [5678 -9012 128] with "ak47"';
    
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    expect($event['killer']['position'])->toBe([-1234, 2345, 64])
        ->and($event['victim']['position'])->toBe([5678, -9012, 128]);
});
```

**GREEN - Create LogParser Service:**
```bash
php artisan make:class Services/LogParser
```

```php
// app/Services/LogParser.php
<?php

namespace App\Services;

class LogParser
{
    private const PATTERNS = [
        'kill' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" (?:\[(-?\d+) (-?\d+) (-?\d+)\] )?killed "(.+?)<(\d+)><(.+?)><(.+?)>" (?:\[(-?\d+) (-?\d+) (-?\d+)\] )?with "(.+?)"(?: \((.+?)\))?/',
        'connect' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><>" connected, address "(.+?):(\d+)"/',
        'disconnect' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" disconnected \(reason "(.+?)"\)/',
        'chat' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" say "(.+?)"/',
        'team_chat' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+?)<(\d+)><(.+?)><(.+?)>" say_team "(.+?)"/',
        'map_change' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): Loading map "(.+?)"/',
        'round_end' => '/^L (\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): World triggered "Round_End"/',
    ];

    public function parse(string $logLine): ?array
    {
        foreach (self::PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $logLine, $matches)) {
                return $this->{"parse" . ucfirst(str_replace('_', '', $type))}($matches);
            }
        }
        
        return null;
    }

    private function parseKill(array $matches): array
    {
        return [
            'type' => 'kill',
            'timestamp' => $this->parseTimestamp($matches[1]),
            'killer' => [
                'name' => $matches[2],
                'id' => $matches[3],
                'steam_id' => $matches[4],
                'team' => $matches[5],
                'position' => isset($matches[6]) ? [(int)$matches[6], (int)$matches[7], (int)$matches[8]] : null,
            ],
            'victim' => [
                'name' => $matches[9],
                'id' => $matches[10],
                'steam_id' => $matches[11],
                'team' => $matches[12],
                'position' => isset($matches[13]) ? [(int)$matches[13], (int)$matches[14], (int)$matches[15]] : null,
            ],
            'weapon' => $matches[16],
            'headshot' => isset($matches[17]) && str_contains($matches[17], 'headshot'),
        ];
    }

    // Implement other parse methods...
}
```

---

#### Day 3-4: Event Processing Queue Tests

**RED - Write Test First:**
```php
// tests/Unit/Jobs/ProcessLogEventTest.php
<?php

use App\Jobs\ProcessLogEvent;
use App\Models\EventFrag;
use App\Models\Player;
use App\Models\Server;
use App\Events\KillFeedEvent;
use Illuminate\Support\Facades\Event;

test('process log event job creates event frag', function () {
    Event::fake();
    
    $server = Server::factory()->create();
    $killer = Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);
    $victim = Player::factory()->create(['steam_id' => 'STEAM_1:0:67890']);
    
    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => true,
    ];
    
    ProcessLogEvent::dispatch($eventData);
    
    $this->assertDatabaseHas('hlstats_events_frags', [
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
        'weapon' => 'ak47',
        'headshot' => true,
    ]);
});

test('process log event updates player statistics', function () {
    $server = Server::factory()->create();
    $killer = Player::factory()->create([
        'steam_id' => 'STEAM_1:0:12345',
        'kills' => 10,
        'skill' => 1000,
    ]);
    $victim = Player::factory()->create([
        'steam_id' => 'STEAM_1:0:67890',
        'deaths' => 5,
    ]);
    
    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => false,
    ];
    
    ProcessLogEvent::dispatchSync($eventData);
    
    expect($killer->fresh()->kills)->toBe(11)
        ->and($victim->fresh()->deaths)->toBe(6);
});

test('process log event creates player if not exists', function () {
    $server = Server::factory()->create();
    
    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => [
            'steam_id' => 'STEAM_1:0:99999',
            'name' => 'NewPlayer',
        ],
        'victim' => [
            'steam_id' => 'STEAM_1:0:88888',
            'name' => 'AnotherPlayer',
        ],
        'weapon' => 'knife',
        'headshot' => false,
    ];
    
    ProcessLogEvent::dispatchSync($eventData);
    
    $this->assertDatabaseHas('hlstats_players', [
        'steam_id' => 'STEAM_1:0:99999',
        'last_name' => 'NewPlayer',
    ]);
    
    $this->assertDatabaseHas('hlstats_players', [
        'steam_id' => 'STEAM_1:0:88888',
        'last_name' => 'AnotherPlayer',
    ]);
});

test('process log event broadcasts kill feed event', function () {
    Event::fake([KillFeedEvent::class]);
    
    $server = Server::factory()->create();
    $killer = Player::factory()->create(['steam_id' => 'STEAM_1:0:12345']);
    $victim = Player::factory()->create(['steam_id' => 'STEAM_1:0:67890']);
    
    $eventData = [
        'type' => 'kill',
        'server_id' => $server->id,
        'killer' => ['steam_id' => 'STEAM_1:0:12345'],
        'victim' => ['steam_id' => 'STEAM_1:0:67890'],
        'weapon' => 'ak47',
        'headshot' => true,
    ];
    
    ProcessLogEvent::dispatchSync($eventData);
    
    Event::assertDispatched(KillFeedEvent::class);
});
```

**GREEN - Create Job:**
```bash
php artisan make:job ProcessLogEvent
```

---

### Week 7: Skill Calculation System

#### Day 1-3: Skill System Tests

**RED - Write Test First:**
```php
// tests/Unit/Services/SkillCalculatorTest.php
<?php

use App\Services\SkillCalculator;
use App\Models\Player;
use App\Models\Weapon;

test('skill increases on kill', function () {
    $killer = Player::factory()->create(['skill' => 1000]);
    $victim = Player::factory()->create(['skill' => 1000]);
    $weapon = Weapon::factory()->create(['modifier' => 1.0]);
    
    $calculator = new SkillCalculator();
    $newSkill = $calculator->calculateKillSkill($killer, $victim, $weapon);
    
    expect($newSkill)->toBeGreaterThan(1000);
});

test('skill decreases on death', function () {
    $killer = Player::factory()->create(['skill' => 1000]);
    $victim = Player::factory()->create(['skill' => 1000]);
    
    $calculator = new SkillCalculator();
    $newSkill = $calculator->calculateDeathSkill($victim, $killer);
    
    expect($newSkill)->toBeLessThan(1000);
});

test('killing higher skilled player gives more skill', function () {
    $killer = Player::factory()->create(['skill' => 1000]);
    $lowSkillVictim = Player::factory()->create(['skill' => 800]);
    $highSkillVictim = Player::factory()->create(['skill' => 1500]);
    $weapon = Weapon::factory()->create(['modifier' => 1.0]);
    
    $calculator = new SkillCalculator();
    
    $skillFromLow = $calculator->calculateKillSkill($killer, $lowSkillVictim, $weapon);
    $skillFromHigh = $calculator->calculateKillSkill($killer, $highSkillVictim, $weapon);
    
    expect($skillFromHigh)->toBeGreaterThan($skillFromLow);
});

test('weapon modifier affects skill gain', function () {
    $killer = Player::factory()->create(['skill' => 1000]);
    $victim = Player::factory()->create(['skill' => 1000]);
    $normalWeapon = Weapon::factory()->create(['modifier' => 1.0]);
    $hardWeapon = Weapon::factory()->create(['modifier' => 2.0]); // Knife
    
    $calculator = new SkillCalculator();
    
    $normalSkill = $calculator->calculateKillSkill($killer, $victim, $normalWeapon);
    $hardSkill = $calculator->calculateKillSkill($killer, $victim, $hardWeapon);
    
    expect($hardSkill)->toBeGreaterThan($normalSkill);
});

test('headshot bonus is applied', function () {
    $killer = Player::factory()->create(['skill' => 1000]);
    $victim = Player::factory()->create(['skill' => 1000]);
    $weapon = Weapon::factory()->create(['modifier' => 1.0]);
    
    $calculator = new SkillCalculator();
    
    $normalKill = $calculator->calculateKillSkill($killer, $victim, $weapon, false);
    $headshotKill = $calculator->calculateKillSkill($killer, $victim, $weapon, true);
    
    expect($headshotKill)->toBeGreaterThan($normalKill);
});

test('skill cannot go below minimum', function () {
    $victim = Player::factory()->create(['skill' => 100]);
    $killer = Player::factory()->create(['skill' => 2000]);
    
    $calculator = new SkillCalculator();
    
    // Die many times
    for ($i = 0; $i < 100; $i++) {
        $newSkill = $calculator->calculateDeathSkill($victim, $killer);
        $victim->skill = $newSkill;
    }
    
    expect($victim->skill)->toBeGreaterThanOrEqual(0);
});
```

**GREEN - Create SkillCalculator Service**

---

## Phase 4: Frontend (Weeks 8-10)

### Week 8: Player Components

#### Day 1-2: PlayerRankings Component

**RED - Write Test First (Vitest):**
```typescript
// resources/js/components/PlayerRankings.test.ts
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PlayerRankings from './PlayerRankings.vue';

describe('PlayerRankings', () => {
  it('renders player rankings table', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'Player1', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
          { id: 2, name: 'Player2', skill: 1500, kills: 80, deaths: 60, kd_ratio: 1.33 },
        ],
      },
    });

    expect(wrapper.find('table').exists()).toBe(true);
    expect(wrapper.findAll('tbody tr')).toHaveLength(2);
  });

  it('displays player name', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'TestPlayer', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
        ],
      },
    });

    expect(wrapper.text()).toContain('TestPlayer');
  });

  it('displays player skill', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'Player1', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
        ],
      },
    });

    expect(wrapper.text()).toContain('2000');
  });

  it('displays KD ratio formatted to 2 decimals', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'Player1', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
        ],
      },
    });

    expect(wrapper.text()).toContain('2.00');
  });

  it('emits player-selected event when row clicked', async () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'Player1', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
        ],
      },
    });

    await wrapper.find('tbody tr').trigger('click');

    expect(wrapper.emitted('player-selected')).toBeTruthy();
    expect(wrapper.emitted('player-selected')?.[0]).toEqual([1]);
  });

  it('shows loading state', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [],
        loading: true,
      },
    });

    expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(true);
  });

  it('shows empty state when no players', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [],
        loading: false,
      },
    });

    expect(wrapper.text()).toContain('No players found');
  });

  it('sorts by skill descending by default', () => {
    const wrapper = mount(PlayerRankings, {
      props: {
        players: [
          { id: 1, name: 'Player1', skill: 1500, kills: 80, deaths: 60, kd_ratio: 1.33 },
          { id: 2, name: 'Player2', skill: 2000, kills: 100, deaths: 50, kd_ratio: 2.0 },
        ],
      },
    });

    const rows = wrapper.findAll('tbody tr');
    expect(rows[0].text()).toContain('Player2'); // Higher skill first
    expect(rows[1].text()).toContain('Player1');
  });
});
```

**GREEN - Create Component:**
```vue
<!-- resources/js/components/PlayerRankings.vue -->
<script setup lang="ts">
import { computed } from 'vue';

interface Player {
  id: number;
  name: string;
  skill: number;
  kills: number;
  deaths: number;
  kd_ratio: number;
}

interface Props {
  players: Player[];
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
});

const emit = defineEmits<{
  'player-selected': [playerId: number];
}>();

const sortedPlayers = computed(() => {
  return [...props.players].sort((a, b) => b.skill - a.skill);
});

const handleRowClick = (playerId: number) => {
  emit('player-selected', playerId);
};
</script>

<template>
  <div class="player-rankings">
    <div v-if="loading" class="loading-state">
      <div data-testid="loading-spinner" class="spinner" />
      <p>Loading rankings...</p>
    </div>

    <div v-else-if="sortedPlayers.length === 0" class="empty-state">
      <p>No players found</p>
    </div>

    <table v-else class="rankings-table">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Player</th>
          <th>Skill</th>
          <th>Kills</th>
          <th>Deaths</th>
          <th>K/D</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(player, index) in sortedPlayers"
          :key="player.id"
          @click="handleRowClick(player.id)"
          class="player-row"
        >
          <td>{{ index + 1 }}</td>
          <td>{{ player.name }}</td>
          <td>{{ player.skill.toFixed(0) }}</td>
          <td>{{ player.kills }}</td>
          <td>{{ player.deaths }}</td>
          <td>{{ player.kd_ratio.toFixed(2) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

**Run Tests:**
```bash
npm run test
```

---

#### Day 3-4: PlayerProfile Component Tests

**RED - Write Test First:**
```typescript
// resources/js/components/PlayerProfile.test.ts
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PlayerProfile from './PlayerProfile.vue';

describe('PlayerProfile', () => {
  const mockPlayer = {
    id: 1,
    name: 'TestPlayer',
    skill: 2000,
    kills: 500,
    deaths: 250,
    kd_ratio: 2.0,
    headshot_percentage: 45.5,
    favorite_weapon: 'ak47',
    weapon_stats: [
      { weapon: 'ak47', kills: 200, deaths: 50 },
      { weapon: 'awp', kills: 150, deaths: 30 },
    ],
    recent_kills: [
      { id: 1, victim: 'Enemy1', weapon: 'ak47', headshot: true },
      { id: 2, victim: 'Enemy2', weapon: 'awp', headshot: false },
    ],
  };

  it('renders player name', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.text()).toContain('TestPlayer');
  });

  it('displays skill points', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.text()).toContain('2000');
  });

  it('displays KD ratio', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.text()).toContain('2.00');
  });

  it('displays headshot percentage', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.text()).toContain('45.5%');
  });

  it('renders weapon statistics table', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.find('[data-testid="weapon-stats"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('ak47');
    expect(wrapper.text()).toContain('awp');
  });

  it('shows favorite weapon badge', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.find('[data-testid="favorite-weapon"]').text()).toContain('ak47');
  });

  it('displays recent kills feed', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    const killFeed = wrapper.find('[data-testid="recent-kills"]');
    expect(killFeed.exists()).toBe(true);
    expect(killFeed.text()).toContain('Enemy1');
    expect(killFeed.text()).toContain('Enemy2');
  });

  it('shows headshot icon for headshot kills', () => {
    const wrapper = mount(PlayerProfile, {
      props: { player: mockPlayer },
    });

    expect(wrapper.find('[data-testid="headshot-icon"]').exists()).toBe(true);
  });
});
```

**GREEN - Implement Component**

---

#### Day 5: KillFeed Component with Real-time Updates

**RED - Write Test First:**
```typescript
// resources/js/components/KillFeed.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import KillFeed from './KillFeed.vue';

// Mock Echo
vi.mock('@/services/echo', () => ({
  echo: {
    channel: vi.fn(() => ({
      listen: vi.fn(),
    })),
  },
}));

describe('KillFeed', () => {
  it('displays kill events', () => {
    const wrapper = mount(KillFeed, {
      props: {
        kills: [
          { id: 1, killer: 'Player1', victim: 'Player2', weapon: 'ak47', headshot: true },
          { id: 2, killer: 'Player3', victim: 'Player4', weapon: 'awp', headshot: false },
        ],
      },
    });

    expect(wrapper.text()).toContain('Player1');
    expect(wrapper.text()).toContain('Player2');
    expect(wrapper.text()).toContain('ak47');
  });

  it('shows headshot icon for headshot kills', () => {
    const wrapper = mount(KillFeed, {
      props: {
        kills: [
          { id: 1, killer: 'Player1', victim: 'Player2', weapon: 'ak47', headshot: true },
        ],
      },
    });

    expect(wrapper.find('[data-testid="headshot"]').exists()).toBe(true);
  });

  it('limits display to max items', () => {
    const manyKills = Array.from({ length: 20 }, (_, i) => ({
      id: i,
      killer: `Player${i}`,
      victim: `Victim${i}`,
      weapon: 'ak47',
      headshot: false,
    }));

    const wrapper = mount(KillFeed, {
      props: {
        kills: manyKills,
        maxItems: 10,
      },
    });

    expect(wrapper.findAll('[data-testid="kill-event"]')).toHaveLength(10);
  });

  it('subscribes to Echo channel on mount', () => {
    const { echo } = require('@/services/echo');
    
    mount(KillFeed, {
      props: {
        game: 'csgo',
        kills: [],
      },
    });

    expect(echo.channel).toHaveBeenCalledWith('game.csgo');
  });

  it('adds new kill to feed when event received', async () => {
    const wrapper = mount(KillFeed, {
      props: {
        game: 'csgo',
        kills: [],
      },
    });

    // Simulate Echo event
    await wrapper.vm.handleKillEvent({
      killer: { name: 'NewKiller' },
      victim: { name: 'NewVictim' },
      weapon: 'awp',
      headshot: true,
    });

    expect(wrapper.text()).toContain('NewKiller');
    expect(wrapper.text()).toContain('NewVictim');
  });
});
```

**GREEN - Implement with Echo integration**

---

### Week 9-10: Remaining Components

Similar TDD approach for:
- WeaponStatistics.vue
- ServerBrowser.vue
- MapStatistics.vue
- LiveStats.vue (real-time dashboard)

---

## Phase 5: Integration & Performance (Weeks 11-12)

### Week 11: Integration Tests

#### Day 1-3: End-to-End Kill Event Flow

**RED - Write Test First:**
```php
// tests/Feature/Integration/KillEventFlowTest.php
<?php

use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use App\Models\EventFrag;
use App\Services\LogParser;
use App\Jobs\ProcessLogEvent;
use App\Events\KillFeedEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

test('complete kill event flow from log to database', function () {
    Event::fake([KillFeedEvent::class]);
    
    // Setup
    $server = Server::factory()->create(['game' => 'csgo']);
    Weapon::factory()->create(['code' => 'ak47', 'modifier' => 1.0]);
    
    // Simulate log line
    $logLine = 'L 02/09/2026 - 12:34:56: "Killer<123><STEAM_1:0:12345><CT>" killed "Victim<456><STEAM_1:0:67890><TERRORIST>" with "ak47" (headshot)';
    
    // Parse
    $parser = new LogParser();
    $event = $parser->parse($logLine);
    
    // Process
    $event['server_id'] = $server->id;
    ProcessLogEvent::dispatchSync($event);
    
    // Verify database
    $this->assertDatabaseHas('hlstats_players', [
        'steam_id' => 'STEAM_1:0:12345',
        'last_name' => 'Killer',
    ]);
    
    $this->assertDatabaseHas('hlstats_players', [
        'steam_id' => 'STEAM_1:0:67890',
        'last_name' => 'Victim',
    ]);
    
    $this->assertDatabaseHas('hlstats_events_frags', [
        'weapon' => 'ak47',
        'headshot' => true,
    ]);
    
    // Verify event broadcast
    Event::assertDispatched(KillFeedEvent::class);
    
    // Verify skill calculation
    $killer = Player::where('steam_id', 'STEAM_1:0:12345')->first();
    expect($killer->skill)->toBeGreaterThan(1000); // Default starting skill
});

test('complete player ranking flow through API', function () {
    // Setup
    $game = Game::factory()->create(['code' => 'csgo']);
    
    // Create players with varying skills
    $topPlayer = Player::factory()->create([
        'game' => 'csgo',
        'last_name' => 'TopPlayer',
        'skill' => 2500,
        'kills' => 500,
        'deaths' => 100,
    ]);
    
    Player::factory()->count(5)->create([
        'game' => 'csgo',
        'skill' => fn() => rand(1000, 2000),
    ]);
    
    // Request rankings
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    $response->assertOk();
    
    // Verify top player is first
    $players = $response->json('data');
    expect($players[0]['name'])->toBe('TopPlayer')
        ->and($players[0]['skill'])->toBe(2500.0);
    
    // Verify caching
    $cacheKey = 'rankings:csgo:1';
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('real-time kill feed is broadcast to frontend', function () {
    Event::fake([KillFeedEvent::class]);
    
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();
    $server = Server::factory()->create();
    
    $frag = EventFrag::factory()->create([
        'killer_id' => $killer->id,
        'victim_id' => $victim->id,
        'server_id' => $server->id,
        'weapon' => 'ak47',
        'headshot' => true,
    ]);
    
    event(new KillFeedEvent($frag));
    
    Event::assertDispatched(KillFeedEvent::class, function ($event) use ($killer, $victim) {
        return $event->broadcastWith()['killer']['name'] === $killer->last_name
            && $event->broadcastWith()['victim']['name'] === $victim->last_name
            && $event->broadcastWith()['headshot'] === true;
    });
});
```

---

#### Day 4-5: Performance Tests

**RED - Write Test First:**
```php
// tests/Feature/Performance/QueryPerformanceTest.php
<?php

use App\Models\Player;
use App\Models\EventFrag;
use Illuminate\Support\Facades\DB;

test('player rankings query completes under 200ms', function () {
    // Create realistic dataset
    Player::factory()->count(1000)->create(['game' => 'csgo']);
    
    DB::enableQueryLog();
    $start = microtime(true);
    
    $rankings = Player::query()
        ->byGame('csgo')
        ->active()
        ->topRanked()
        ->limit(100)
        ->get();
    
    $duration = (microtime(true) - $start) * 1000; // Convert to ms
    
    expect($rankings)->toHaveCount(100)
        ->and($duration)->toBeLessThan(200);
    
    // Verify index usage
    $queries = DB::getQueryLog();
    expect($queries[0]['query'])->toContain('idx_game_skill');
})->group('performance');

test('event frag insertion handles burst load', function () {
    $killer = Player::factory()->create();
    $victim = Player::factory()->create();
    $server = Server::factory()->create();
    
    $start = microtime(true);
    
    // Simulate 100 kills in quick succession
    for ($i = 0; $i < 100; $i++) {
        EventFrag::create([
            'killer_id' => $killer->id,
            'victim_id' => $victim->id,
            'server_id' => $server->id,
            'weapon' => 'ak47',
            'headshot' => (bool) rand(0, 1),
            'event_time' => now(),
        ]);
    }
    
    $duration = (microtime(true) - $start) * 1000;
    
    expect($duration)->toBeLessThan(1000) // Should complete in under 1 second
        ->and(EventFrag::count())->toBe(100);
})->group('performance');

test('IP geolocation query uses index', function () {
    // Create GeoIP data
    DB::table('hlstats_geoip_blocks')->insert([
        ['start_ip_num' => 1000000, 'end_ip_num' => 2000000, 'location_id' => 1],
        ['start_ip_num' => 2000001, 'end_ip_num' => 3000000, 'location_id' => 2],
    ]);
    
    DB::enableQueryLog();
    
    $testIp = 1500000;
    $location = DB::table('hlstats_geoip_blocks')
        ->where('start_ip_num', '<=', $testIp)
        ->where('end_ip_num', '>=', $testIp)
        ->first();
    
    $queries = DB::getQueryLog();
    
    // Verify EXPLAIN shows index usage
    $explain = DB::select("EXPLAIN " . $queries[0]['query'])[0];
    expect($explain->key)->toBe('idx_ip_range');
})->group('performance');
```

---

### Week 12: Load Testing & Optimization

#### Day 1-3: Load Tests

```php
// tests/Feature/Load/ConcurrentUsersTest.php
<?php

use Illuminate\Support\Facades\Artisan;

test('system handles 100 concurrent ranking requests', function () {
    // Seed data
    Player::factory()->count(1000)->create(['game' => 'csgo']);
    
    $responses = [];
    $processes = [];
    
    // Simulate 100 concurrent requests
    for ($i = 0; $i < 100; $i++) {
        $processes[] = proc_open(
            'php artisan tinker --execute="app(\'App\Http\Controllers\Api\PlayerController\')->rankings(new App\Http\Requests\PlayerRankingRequest([\'game\' => \'csgo\']))"',
            [STDIN, STDOUT, STDERR],
            $pipes
        );
    }
    
    // Wait for all to complete
    foreach ($processes as $process) {
        proc_close($process);
    }
    
    // Verify system remained responsive
    $response = $this->getJson('/api/players/rankings?game=csgo');
    $response->assertOk();
})->group('load');

test('log parser handles 1000 events per second', function () {
    $server = Server::factory()->create();
    $players = Player::factory()->count(20)->create();
    
    Queue::fake();
    
    $start = microtime(true);
    
    // Generate 1000 log events
    for ($i = 0; $i < 1000; $i++) {
        $logLine = sprintf(
            'L 02/09/2026 - 12:34:56: "Player%d<123><STEAM_1:0:%d><CT>" killed "Player%d<456><STEAM_1:0:%d><TERRORIST>" with "ak47"',
            rand(1, 20),
            rand(10000, 99999),
            rand(1, 20),
            rand(10000, 99999)
        );
        
        $parser = new LogParser();
        $event = $parser->parse($logLine);
        
        ProcessLogEvent::dispatch($event);
    }
    
    $duration = microtime(true) - $start;
    
    expect($duration)->toBeLessThan(1.0); // Process 1000 events in under 1 second
    
    Queue::assertPushed(ProcessLogEvent::class, 1000);
})->group('load');
```

---

## Phase 6: Deployment & Monitoring (Weeks 13-14)

### Week 13: Production Setup

#### Day 1-2: Deployment Tests

```php
// tests/Feature/Deployment/HealthCheckTest.php
<?php

test('health endpoint returns ok', function () {
    $response = $this->get('/health');
    
    $response->assertOk()
        ->assertJson([
            'status' => 'healthy',
            'database' => 'connected',
            'redis' => 'connected',
            'queue' => 'running',
        ]);
});

test('database connection is healthy', function () {
    expect(DB::connection()->getPdo())->toBeTruthy();
});

test('redis connection is healthy', function () {
    expect(Redis::ping())->toBe('PONG');
});

test('queue workers are running', function () {
    // Dispatch test job
    $testJob = new class {
        public function handle() {
            Cache::put('queue_test', true, 60);
        }
    };
    
    dispatch($testJob);
    
    // Wait and verify
    sleep(2);
    expect(Cache::get('queue_test'))->toBeTrue();
});
```

---

#### Day 3-5: Monitoring Tests

```php
// tests/Feature/Monitoring/MetricsTest.php
<?php

test('tracks API response times', function () {
    $response = $this->getJson('/api/players/rankings?game=csgo');
    
    // Verify response time header exists
    expect($response->headers->has('X-Response-Time'))->toBeTrue();
    
    $responseTime = (float) $response->headers->get('X-Response-Time');
    expect($responseTime)->toBeGreaterThan(0);
});

test('logs slow queries', function () {
    DB::listen(function ($query) {
        if ($query->time > 1000) { // Over 1 second
            Log::warning('Slow query detected', [
                'query' => $query->sql,
                'time' => $query->time,
            ]);
        }
    });
    
    // Execute potentially slow query
    Player::with('kills', 'deaths')->limit(1000)->get();
    
    // Verify logging works
    expect(true)->toBeTrue();
});
```

---

### Week 14: Final Integration & Documentation

#### Day 1-3: Full System Tests

```php
// tests/Feature/System/CompleteFlowTest.php
<?php

test('complete game session from connection to disconnection', function () {
    $server = Server::factory()->create();
    $parser = new LogParser();
    
    // Player connects
    $connectLog = 'L 02/09/2026 - 12:00:00: "TestPlayer<123><STEAM_1:0:12345><>" connected, address "192.168.1.1:27005"';
    $connectEvent = $parser->parse($connectLog);
    ProcessLogEvent::dispatchSync(array_merge($connectEvent, ['server_id' => $server->id]));
    
    $player = Player::where('steam_id', 'STEAM_1:0:12345')->first();
    expect($player)->not->toBeNull();
    
    // Player gets kills
    for ($i = 0; $i < 10; $i++) {
        $killLog = sprintf(
            'L 02/09/2026 - 12:%02d:00: "TestPlayer<123><STEAM_1:0:12345><CT>" killed "Bot%d<999><BOT><TERRORIST>" with "ak47"',
            $i,
            $i
        );
        $killEvent = $parser->parse($killLog);
        ProcessLogEvent::dispatchSync(array_merge($killEvent, ['server_id' => $server->id]));
    }
    
    // Verify stats updated
    $player->refresh();
    expect($player->kills)->toBe(10)
        ->and($player->skill)->toBeGreaterThan(1000);
    
    // Player disconnects
    $disconnectLog = 'L 02/09/2026 - 12:30:00: "TestPlayer<123><STEAM_1:0:12345><CT>" disconnected (reason "Disconnect by user.")';
    $disconnectEvent = $parser->parse($disconnectLog);
    ProcessLogEvent::dispatchSync(array_merge($disconnectEvent, ['server_id' => $server->id]));
    
    // Verify session created
    $this->assertDatabaseHas('hlstats_events_connects', [
        'player_id' => $player->id,
    ]);
    
    $this->assertDatabaseHas('hlstats_events_disconnects', [
        'player_id' => $player->id,
    ]);
});
```

---

## Testing Strategy Summary

### Test Coverage Goals

| Component | Unit Tests | Integration Tests | E2E Tests | Coverage Target |
|-----------|-----------|-------------------|-----------|-----------------|
| Models | ✅ Required | ✅ Required | - | 90%+ |
| Controllers | ✅ Required | ✅ Required | ✅ Optional | 85%+ |
| Services | ✅ Required | ✅ Required | - | 90%+ |
| Jobs/Queues | ✅ Required | ✅ Required | - | 85%+ |
| API Endpoints | - | ✅ Required | ✅ Required | 90%+ |
| Vue Components | ✅ Required | ✅ Optional | - | 80%+ |
| Log Parser | ✅ Required | ✅ Required | ✅ Required | 95%+ |

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage --min=80

# Run specific test suite
php artisan test --testsuite=Feature

# Run specific test group
php artisan test --group=performance

# Run tests in parallel
php artisan test --parallel

# Frontend tests
npm run test
npm run test:coverage

# E2E tests (if using Playwright)
npm run test:e2e
```

### Continuous Integration

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo_mysql
      
      - name: Install Dependencies
        run: composer install
      
      - name: Run Tests
        run: php artisan test --coverage --min=80
      
      - name: Frontend Tests
        run: |
          npm install
          npm run test:coverage
```

---

## Benefits of TDD Approach

1. **Confidence:** Every feature has tests before implementation
2. **Documentation:** Tests serve as living documentation
3. **Refactoring Safety:** Can refactor without fear of breaking things
4. **Better Design:** Writing tests first leads to better API design
5. **Regression Prevention:** Bugs fixed with tests won't resurface
6. **Faster Development:** Catch issues immediately, not in production

---

## Next Steps

1. **Week 1 Starts:** Begin with database migration tests
2. **Daily Standup:** Review progress, blockers, test results
3. **Weekly Review:** Demo working features, review test coverage
4. **Continuous Deployment:** Push to staging after all tests pass

Ready to start implementation? Let's begin with Week 1, Day 1! 🚀
