<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventFragResource;
use App\Models\EventFrag;
use Illuminate\Http\Request;

class FragController extends Controller
{
    public function index(Request $request)
    {
        $frags = EventFrag::query()
            ->with(['killer', 'victim', 'weapon', 'server'])
            ->latest('event_time')
            ->paginate($request->get('per_page', 50));

        return EventFragResource::collection($frags);
    }

    public function recent(Request $request)
    {
        $limit = $request->get('limit', 50);

        $frags = EventFrag::query()
            ->with(['killer', 'victim', 'weapon', 'server'])
            ->recent()
            ->limit($limit)
            ->get();

        return EventFragResource::collection($frags);
    }
}
