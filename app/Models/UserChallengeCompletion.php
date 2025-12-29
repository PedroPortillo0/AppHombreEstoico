<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChallengeCompletion extends Model
{
    protected $fillable = [
        'id',
        'user_id',
        'name',
        'level',
        'objective',
        'points',
        'completed_at'
    ];

    protected $casts = [
        'points' => 'integer',
        'completed_at' => 'datetime'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
