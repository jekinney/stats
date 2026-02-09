<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRankingRequest;
use App\Http\Requests\PlayerSearchRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class PlayerController extends Controller
{
    public function rankings(PlayerRankingRequest $request)
    {
        $validated = $request->validated();

        $cacheKey = "rankings:{$validated['game']}:page:{$request->get('page', 1)}:per_page:{$request->get('per_page', 20)}";

        $players = Cache::remember($cacheKey, 300, function () use ($validated, $request) {
            return Player::query()
                ->byGame($validated['game'])
                ->active()
                ->topRanked()
                ->paginate($request->get('per_page', 20));
        });

        return PlayerResource::collection($players);
    }

    public function show(Player $player)
    {
        $player->loadCount([
            'kills',
            'deaths',
            'kills as headshot_count' => fn ($q) => $q->where('headshot', true),
        ]);

        $player->load([
            'kills' => fn ($q) => $q->with('weapon')->latest('event_time')->limit(10),
        ]);

        return new PlayerResource($player);
    }

    public function search(PlayerSearchRequest $request)
    {
        $validated = $request->validated();

        $query = Player::query()
            ->where('last_name', 'like', '%'.$validated['q'].'%')
            ->active();

        if (isset($validated['game'])) {
            $query->byGame($validated['game']);
        }

        $players = $query->paginate($request->get('per_page', 20));

        return PlayerResource::collection($players);
    }
}
