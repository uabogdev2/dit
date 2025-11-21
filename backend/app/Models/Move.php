<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Move extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'r',
        'c',
        'o',
        'move_idempotency_key',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
