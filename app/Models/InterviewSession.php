<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterviewSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_title',
        'overall_score',
    ];

    public function questions()
    {
        return $this->hasMany(InterviewQuestion::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
