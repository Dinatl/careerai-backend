<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', '30d');
        $days = ['7d' => 7, '30d' => 30, '90d' => 90][$period] ?? 30;
        $from = now()->subDays($days);
        $user = $request->user()->load(['skills', 'badges', 'points']);

        $atsScores = $user->cvAnalyses()
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($analysis) => [
                'date' => $analysis->created_at->format('Y-m-d'),
                'score' => $analysis->score,
            ]);

        $interviews = $user->interviewSessions()
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->get();

        $interviewScores = $interviews->map(fn ($session) => [
            'date' => $session->created_at->format('Y-m-d'),
            'score' => $session->overall_score,
            'job_title' => $session->job_title,
        ]);

        $simulationsByWeek = $interviews
            ->groupBy(fn ($session) => $session->created_at->format('o-\WW'))
            ->map(fn ($items, $week) => ['week' => $week, 'count' => $items->count()])
            ->values();

        $topSkills = $user->skills
            ->take(8)
            ->map(fn ($skill) => ['name' => $skill->name, 'mentions' => substr_count(mb_strtolower($this->allCvText($user)), mb_strtolower($skill->name)) ?: 1])
            ->sortByDesc('mentions')
            ->values();

        BadgeController::ensureDefaultBadges();
        $earnedBadges = $user->badges()->count();
        $totalBadges = Badge::count();
        $points = $user->points()->firstOrCreate(['user_id' => $user->id], ['points' => 0, 'level' => 'Debutant']);

        return response()->json([
            'period' => $period,
            'ats_scores' => $atsScores,
            'interview_scores' => $interviewScores,
            'simulations_by_week' => $simulationsByWeek,
            'average_interview_score' => round((float) $interviews->avg('overall_score')),
            'top_skills' => $topSkills,
            'badges_progress' => [
                'earned' => $earnedBadges,
                'total' => $totalBadges,
                'points' => $points->points,
                'level' => $points->level,
                'percentage' => $totalBadges > 0 ? round(($earnedBadges / $totalBadges) * 100) : 0,
            ],
        ]);
    }

    private function allCvText($user): string
    {
        return $user->cvAnalyses()->latest()->limit(5)->pluck('cv_text')->join("\n");
    }
}
