<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;
    
    protected $table = 'educations';

    protected $fillable = [
        'user_id',
        'degree',
        'school',
        'year',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
