<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MatchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/matches",
     *     summary="List user matches",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of matches")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Get matches where user is a player
        $matches = GameMatch::whereHas('players', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with('players')->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($matches);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/matches",
     *     summary="Create a new match",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="grid_size", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Match created")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'grid_size' => 'integer|min:2|max:10'
        ]);

        $gridSize = $request->input('grid_size', 3);
        $user = $request->user();

        $match = DB::transaction(function () use ($user, $gridSize) {
            $match = GameMatch::create([
                'code' => strtoupper(Str::random(6)),
                'grid_size' => $gridSize,
                'status' => 'waiting',
                'current_turn_user_id' => $user->id, // Creator starts? Or random later. Let's say creator starts for now.
                'board_state' => $this->initializeBoard($gridSize)
            ]);

            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $user->id,
                'order_index' => 0,
                'score' => 0
            ]);

            return $match;
        });

        // Broadcast event via Redis
        $this->publishToRedis('match.created', $match);

        return response()->json($match->load('players'), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/matches/{id}",
     *     summary="Get match details",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Match details")
     * )
     */
    public function show($id)
    {
        $match = GameMatch::with(['players', 'moves', 'squares'])->findOrFail($id);
        return response()->json($match);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/matches/{code}/join",
     *     summary="Join a match by code",
     *     tags={"Matches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Joined successfully")
     * )
     */
    public function join(Request $request, $code)
    {
        $user = $request->user();
        $match = GameMatch::where('code', $code)->firstOrFail();

        if ($match->status !== 'waiting') {
            return response()->json(['message' => 'Match already started or finished'], 400);
        }

        // Check if already joined
        if ($match->players()->where('user_id', $user->id)->exists()) {
             return response()->json($match->load('players'));
        }

        // Check max players (2 for now)
        if ($match->players()->count() >= 2) {
            return response()->json(['message' => 'Match full'], 400);
        }

        DB::transaction(function () use ($match, $user) {
            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $user->id,
                'order_index' => 1,
                'score' => 0
            ]);

            $match->status = 'playing';
            $match->save();
        });

        $match->load('players');

        // Broadcast event
        $this->publishToRedis('match.joined', $match);

        return response()->json($match);
    }

    private function initializeBoard($size)
    {
        // Simple representation if needed, or rely on moves
        return ['size' => $size];
    }

    private function publishToRedis($event, $data)
    {
        try {
            // Format: { "event": "event_name", "data": {...}, "room": "match_ID" or "lobby" }
            $room = isset($data->id) ? "match_{$data->id}" : "lobby";
            if ($event === 'match.created') $room = "lobby"; // Broadcast creation to lobby? Or just user?

            $payload = json_encode([
                'event' => $event,
                'data' => $data,
                'room' => $room
            ]);

            // Using Predis client manually or Redis facade
            Redis::publish('dots_events', $payload);
        } catch (\Exception $e) {
            // Log redis error but don't fail request
            \Log::error("Redis publish failed: " . $e->getMessage());
        }
    }
}
