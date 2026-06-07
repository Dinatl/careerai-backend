<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'cv_text',
        'score',
        'strengths',
        'weaknesses',
        'suggestions',
    ];

    protected $casts = [
        'strengths' => 'array',
        'weaknesses' => 'array',
        'suggestions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
