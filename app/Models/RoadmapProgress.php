<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadmapProgress extends Model
{
    use HasFactory;

    protected $table = 'roadmap_progress';

    protected $fillable = [
        'user_id',
        'roadmap_id',
        'step_id',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime',
    ];
}
