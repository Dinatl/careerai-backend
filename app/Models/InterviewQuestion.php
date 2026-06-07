<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterviewQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'question',
        'user_answer',
        'ai_feedback',
        'star_score',
    ];

    public function session()
    {
        return $this->belongsTo(InterviewSession::class, 'session_id');
    }
}
