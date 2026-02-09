<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapStatisticsRequest;
use App\Http\Resources\MapStatisticsResource;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    public function statistics(MapStatisticsRequest $request)
    {
        $statistics = DB::table('event_frags')
            ->join('servers', 'event_frags.server_id', '=', 'servers.id')
            ->where('servers.game_code', $request->validated('game'))
            ->select(
                'event_frags.map',
                DB::raw('COUNT(event_frags.id) as kills')
            )
            ->groupBy('event_frags.map')
            ->orderBy('kills', 'desc')
            ->paginate($request->input('per_page', 20));

        return MapStatisticsResource::collection($statistics);
    }
}
