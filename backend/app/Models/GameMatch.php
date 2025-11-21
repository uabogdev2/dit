<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'code',
        'grid_size',
        'status',
        'current_turn_user_id',
        'winner_user_id',
        'board_state',
    ];

    protected $casts = [
        'board_state' => 'array',
    ];

    public function players()
    {
        return $this->belongsToMany(User::class, 'match_players', 'match_id', 'user_id')
                    ->withPivot('score', 'order_index')
                    ->withTimestamps();
    }

    public function moves()
    {
        return $this->hasMany(Move::class, 'match_id');
    }

    public function squares()
    {
        return $this->hasMany(Square::class, 'match_id');
    }
}
