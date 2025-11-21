<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\GameMatch;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Redis;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_game_flow()
    {
        // Mock Redis
        Redis::shouldReceive('publish')->andReturn(true);

        // 1. Setup Users
        $user1 = User::factory()->create(['name' => 'Player 1', 'firebase_uid' => 'uid1']);
        $user2 = User::factory()->create(['name' => 'Player 2', 'firebase_uid' => 'uid2']);

        // 2. Player 1 creates match
        Sanctum::actingAs($user1);
        $response = $this->postJson('/api/v1/matches', ['grid_size' => 2]);
        $response->assertStatus(201);
        $matchId = $response->json('id');
        $code = $response->json('code');

        // 3. Player 2 joins match
        Sanctum::actingAs($user2);
        $response = $this->postJson("/api/v1/matches/{$code}/join");
        $response->assertStatus(200);

        // 4. Player 1 plays a move (Horizontal Top-Left) -> No square completed -> Turn P2
        Sanctum::actingAs($user1);
        $response = $this->postJson("/api/v1/matches/{$matchId}/moves", [
            'r' => 0, 'c' => 0, 'o' => 'h'
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('moves', [
            'match_id' => $matchId,
            'r' => 0,
            'c' => 0,
            'o' => 'h'
        ]);

        // 5. Player 2 plays a move (Vertical Top-Left) -> No square completed -> Turn P1
        Sanctum::actingAs($user2);
        $response = $this->postJson("/api/v1/matches/{$matchId}/moves", [
            'r' => 0, 'c' => 0, 'o' => 'v'
        ]);
        $response->assertStatus(200);

        // 6. Player 1 tries to play duplicate move (Horizontal Top-Left)
        Sanctum::actingAs($user1);
        $response = $this->postJson("/api/v1/matches/{$matchId}/moves", [
            'r' => 0, 'c' => 0, 'o' => 'h'
        ]);
        $response->assertStatus(400); // Edge already taken
    }
}
