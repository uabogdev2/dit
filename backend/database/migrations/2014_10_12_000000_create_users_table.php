<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid')->unique()->nullable(); // Nullable initially if we create user before firebase auth, but mostly unique
            $table->string('name'); // Display Name
            $table->string('email')->unique()->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('stats')->nullable(); // { "wins": 0, "losses": 0, "draws": 0 }
            $table->string('password')->nullable(); // Can be null for Firebase Auth
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
