<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_offer_text',
        'match_score',
        'matched_skills',
        'missing_skills',
        'recommendations',
    ];

    protected $casts = [
        'matched_skills' => 'array',
        'missing_skills' => 'array',
        'recommendations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
