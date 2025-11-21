<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $table = 'match_players';

    protected $fillable = [
        'match_id',
        'user_id',
        'order_index',
        'score',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
