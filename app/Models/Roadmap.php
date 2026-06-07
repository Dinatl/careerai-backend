<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roadmap extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'riasec_type',
        'steps',
    ];

    protected $casts = [
        'steps' => 'array',
    ];

    public function progress()
    {
        return $this->hasMany(RoadmapProgress::class);
    }
}
