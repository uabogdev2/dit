<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MoveController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('auth/firebase', [AuthController::class, 'firebaseAuth']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);

        // Matches
        Route::get('matches', [MatchController::class, 'index']);
        Route::post('matches', [MatchController::class, 'store']);
        Route::get('matches/{id}', [MatchController::class, 'show']);
        Route::post('matches/{code}/join', [MatchController::class, 'join']);

        // Moves
        Route::post('matches/{id}/moves', [MoveController::class, 'store']);
    });
});
