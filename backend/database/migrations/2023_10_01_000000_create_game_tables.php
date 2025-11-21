<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->nullable(); // Join code (e.g. 6 chars)
            $table->integer('grid_size')->default(3); // 3x3 by default
            $table->string('status')->default('waiting'); // waiting, playing, finished
            $table->foreignId('current_turn_user_id')->nullable()->constrained('users');
            $table->foreignId('winner_user_id')->nullable()->constrained('users');
            $table->json('board_state')->nullable(); // Optional full board state dump
            $table->timestamps();
        });

        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('order_index')->default(0);
            $table->integer('score')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });

        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');

            // Columns instead of JSON for better indexing and compatibility
            $table->integer('r');
            $table->integer('c');
            $table->string('o', 1); // 'h' or 'v'

            $table->string('move_idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'move_idempotency_key']);
            // Ensure uniqueness of move in a match
            $table->unique(['match_id', 'r', 'c', 'o']);
        });

        Schema::create('squares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('owner_user_id')->constrained('users');

            // Also using columns for squares
            $table->integer('r');
            $table->integer('c');

            $table->timestamps();

            $table->unique(['match_id', 'r', 'c']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('platform'); // ios, android
            $table->string('token');
            $table->timestamps();
        });

        Schema::create('app_configs', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('squares');
        Schema::dropIfExists('moves');
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('matches');
    }
};
