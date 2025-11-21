<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Move;
use App\Models\Square;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MoveController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/matches/{id}/moves",
     *     summary="Play a move",
     *     tags={"Moves"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="r", type="integer"),
     *             @OA\Property(property="c", type="integer"),
     *             @OA\Property(property="o", type="string", enum={"h", "v"}),
     *             @OA\Property(property="move_idempotency_key", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Move accepted")
     * )
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'r' => 'required|integer',
            'c' => 'required|integer',
            'o' => 'required|in:h,v',
            'move_idempotency_key' => 'nullable|string'
        ]);

        $user = $request->user();
        $match = GameMatch::with('players')->findOrFail($id);

        if ($match->status !== 'playing') {
            return response()->json(['message' => 'Match not in progress'], 400);
        }

        if ($match->current_turn_user_id !== $user->id) {
            return response()->json(['message' => 'Not your turn'], 403);
        }

        // Check coordinates validity
        $size = $match->grid_size;
        $r = $request->r;
        $c = $request->c;
        $o = $request->o;

        $valid = false;
        if ($o === 'h') {
            // Horizontal: r in [0, size], c in [0, size-1]
            if ($r >= 0 && $r <= $size && $c >= 0 && $c < $size) $valid = true;
        } else {
            // Vertical: r in [0, size-1], c in [0, size]
            if ($r >= 0 && $r < $size && $c >= 0 && $c <= $size) $valid = true;
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid coordinates'], 400);
        }

        // Check idempotency key first
        if ($request->move_idempotency_key) {
            $existing = Move::where('match_id', $match->id)
                            ->where('move_idempotency_key', $request->move_idempotency_key)
                            ->first();
            if ($existing) return response()->json($existing);
        }

        // Check if edge taken using simple columns
        $edgeTaken = Move::where('match_id', $match->id)
                         ->where('r', $r)
                         ->where('c', $c)
                         ->where('o', $o)
                         ->exists();

        if ($edgeTaken) {
            return response()->json(['message' => 'Edge already taken'], 400);
        }

        DB::beginTransaction();
        try {
            $move = Move::create([
                'match_id' => $match->id,
                'user_id' => $user->id,
                'r' => $r,
                'c' => $c,
                'o' => $o,
                'move_idempotency_key' => $request->move_idempotency_key
            ]);

            // Check for completed squares
            $completedSquares = $this->checkCompletedSquares($match, $r, $c, $o);

            $turnChanged = false;
            if (count($completedSquares) > 0) {
                // Player scores!
                MatchPlayer::where('match_id', $match->id)
                           ->where('user_id', $user->id)
                           ->increment('score', count($completedSquares));

                foreach ($completedSquares as $sqCoords) {
                    Square::create([
                        'match_id' => $match->id,
                        'owner_user_id' => $user->id,
                        'r' => $sqCoords['r'],
                        'c' => $sqCoords['c']
                    ]);
                }
                // Turn does NOT change
            } else {
                // Turn changes
                $otherPlayer = $match->players->where('id', '!=', $user->id)->first();
                if ($otherPlayer) {
                    $match->current_turn_user_id = $otherPlayer->id;
                    $match->save();
                    $turnChanged = true;
                }
            }

            // Check win condition (total squares = size * size)
            $totalSquares = Square::where('match_id', $match->id)->count();
            if ($totalSquares >= ($size * $size)) {
                $match->status = 'finished';

                // Determine winner
                $players = MatchPlayer::where('match_id', $match->id)->get();
                $p1 = $players[0];
                $p2 = $players[1];

                if ($p1->score > $p2->score) $match->winner_user_id = $p1->user_id;
                elseif ($p2->score > $p1->score) $match->winner_user_id = $p2->user_id;
                else $match->winner_user_id = null; // Draw

                $match->save();

                $this->publishToRedis('match.finished', $match);
            } else {
                $this->publishToRedis('move.played', [
                    'move' => $move,
                    'match_id' => $match->id,
                    'next_turn_user_id' => $match->current_turn_user_id,
                    'squares_completed' => $completedSquares
                ]);
            }

            DB::commit();

            return response()->json([
                'move' => $move,
                'squares_completed' => $completedSquares,
                'next_turn_user_id' => $match->current_turn_user_id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function checkCompletedSquares($match, $r, $c, $o)
    {
        $completed = [];
        $size = $match->grid_size;

        // Helper to check if an edge exists using DB (simple query is fast enough for now, or cache)
        // Optimization: eager load all moves for this match into a Collection first
        static $movesCache = null;
        if ($movesCache === null) {
             $movesCache = Move::where('match_id', $match->id)->get();
        } else {
             // Add current move to cache
             $movesCache->push((object)['r' => $r, 'c' => $c, 'o' => $o]);
        }

        // We need to ensure we query FRESH data if transaction isolates, but here we just created it.
        // However, to be safe, let's just query DB or use the pushed logic.
        // Let's query DB for simplicity but add the current move manually to checks since commit hasn't happened?
        // Actually we are inside transaction, queries see uncommitted data in same transaction usually.
        // But let's just pass current move coords to checker.

        $hasEdge = function($tr, $tc, $to) use ($match, $r, $c, $o) {
            if ($tr == $r && $tc == $c && $to == $o) return true; // The move we just made
            return Move::where('match_id', $match->id)
                       ->where('r', $tr)->where('c', $tc)->where('o', $to)
                       ->exists();
        };

        // If horizontal (r, c), it can be the top of square (r, c) OR bottom of square (r-1, c)
        if ($o === 'h') {
            // Check Square (r, c) (Below the edge)
            if ($r < $size) {
                // Needs: Top(this), Bottom(r+1, c, h), Left(r, c, v), Right(r, c+1, v)
                if ($hasEdge($r+1, $c, 'h') && $hasEdge($r, $c, 'v') && $hasEdge($r, $c+1, 'v')) {
                    $completed[] = ['r' => $r, 'c' => $c];
                }
            }
            // Check Square (r-1, c) (Above the edge)
            if ($r > 0) {
                // This edge is Bottom of (r-1, c).
                // Needs: Top(r-1, c, h), Bottom(this), Left(r-1, c, v), Right(r-1, c+1, v)
                if ($hasEdge($r-1, $c, 'h') && $hasEdge($r-1, $c, 'v') && $hasEdge($r-1, $c+1, 'v')) {
                    $completed[] = ['r' => $r-1, 'c' => $c];
                }
            }
        }
        // If vertical (r, c), it can be Left of square (r, c) OR Right of square (r, c-1)
        else {
            // Check Square (r, c) (Right of the edge)
            if ($c < $size) {
                // Needs: Left(this), Right(r, c+1, v), Top(r, c, h), Bottom(r+1, c, h)
                if ($hasEdge($r, $c+1, 'v') && $hasEdge($r, $c, 'h') && $hasEdge($r+1, $c, 'h')) {
                    $completed[] = ['r' => $r, 'c' => $c];
                }
            }
            // Check Square (r, c-1) (Left of the edge)
            if ($c > 0) {
                // This edge is Right of (r, c-1).
                // Needs: Left(r, c-1, v), Right(this), Top(r, c-1, h), Bottom(r+1, c-1, h)
                if ($hasEdge($r, $c-1, 'v') && $hasEdge($r, $c-1, 'h') && $hasEdge($r+1, $c-1, 'h')) {
                    $completed[] = ['r' => $r, 'c' => $c-1];
                }
            }
        }

        return $completed;
    }

    private function publishToRedis($event, $data)
    {
        try {
            $room = isset($data['match_id']) ? "match_{$data['match_id']}" : (isset($data->id) ? "match_{$data->id}" : "lobby");
            $payload = json_encode([
                'event' => $event,
                'data' => $data,
                'room' => $room
            ]);
            Redis::publish('dots_events', $payload);
        } catch (\Exception $e) {
            \Log::error("Redis publish failed: " . $e->getMessage());
        }
    }
}
