<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function coverLetters()
    {
        return $this->hasMany(CoverLetter::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function cvAnalyses()
    {
        return $this->hasMany(CvAnalysis::class);
    }

    public function cvComparisons()
    {
        return $this->hasMany(CvComparison::class);
    }

    public function badges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function points()
    {
        return $this->hasOne(UserPoint::class);
    }

    public function interviewSessions()
    {
        return $this->hasMany(InterviewSession::class);
    }

    public function roadmaps()
    {
        return $this->hasMany(Roadmap::class);
    }

    public function portfolio()
    {
        return $this->hasOne(Portfolio::class);
    }
}
