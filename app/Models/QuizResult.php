<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personality_type',
        'recommended_jobs',
        'score_data',
    ];

    protected $casts = [
        'recommended_jobs' => 'array',
        'score_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
