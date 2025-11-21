<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Square extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'owner_user_id',
        'r',
        'c'
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
