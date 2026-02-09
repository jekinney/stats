<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServerListRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use Carbon\Carbon;

class ServerController extends Controller
{
    public function index(ServerListRequest $request)
    {
        $query = Server::query()
            ->where('game_code', $request->validated('game'))
            ->where('enabled', true);

        if ($request->boolean('online')) {
            $query->where('last_activity', '>', Carbon::now()->subMinutes(5));
        }

        return ServerResource::collection(
            $query->paginate($request->input('per_page', 20))
        );
    }

    public function show(Server $server)
    {
        return new ServerResource($server);
    }
}
