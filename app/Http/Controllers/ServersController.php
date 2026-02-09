<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Inertia\Inertia;
use Inertia\Response;

class ServersController extends Controller
{
    public function index(): Response
    {
        $servers = Server::withCount('eventFrags as total_frags')
            ->get()
            ->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'address' => $server->address,
                'port' => $server->port,
                'game_code' => $server->game_code,
                'active' => $server->enabled,
                'total_frags' => $server->total_frags ?? 0,
            ]);

        return Inertia::render('Servers/Index', [
            'servers' => $servers,
        ]);
    }
}
