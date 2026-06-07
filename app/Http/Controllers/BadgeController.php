<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserPoint;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    private const BADGES = [
        ['action_key' => 'first_login', 'name' => 'Premiere connexion', 'description' => 'Creer un compte et entrer dans CareerAI.', 'icon' => 'Sparkles', 'points_required' => 10],
        ['action_key' => 'cv_created', 'name' => 'CV cree', 'description' => 'Generer ou telecharger un premier CV.', 'icon' => 'FileText', 'points_required' => 50],
        ['action_key' => 'quiz_completed', 'name' => 'Quiz complete', 'description' => 'Terminer le quiz RIASEC.', 'icon' => 'Target', 'points_required' => 30],
        ['action_key' => 'simulated_interview', 'name' => 'Entretien simule', 'description' => 'Faire une simulation d entretien.', 'icon' => 'Video', 'points_required' => 40],
        ['action_key' => 'profile_complete', 'name' => 'Profil complet', 'description' => 'Renseigner les informations essentielles du profil.', 'icon' => 'Flame', 'points_required' => 100],
        ['action_key' => 'ats_analysis', 'name' => 'Score ATS', 'description' => 'Analyser un CV avec le moteur ATS.', 'icon' => 'Gauge', 'points_required' => 20],
        ['action_key' => 'expert', 'name' => 'Expert', 'description' => 'Atteindre 500 points sur la plateforme.', 'icon' => 'Rocket', 'points_required' => 200],
    ];

    public function index(Request $request)
    {
        self::ensureDefaultBadges();
        self::checkProfileCompletion($request->user());
        self::checkExpertBadge($request->user());

        return response()->json(self::payloadFor($request->user()));
    }

    public function check(Request $request)
    {
        $request->validate([
            'action' => 'nullable|string',
        ]);

        self::ensureDefaultBadges();

        if ($request->filled('action')) {
            self::awardFor($request->user(), $request->action);
        }

        self::checkProfileCompletion($request->user());
        self::checkExpertBadge($request->user());

        return response()->json(self::payloadFor($request->user()));
    }

    public static function awardFor(User $user, string $actionKey): void
    {
        self::ensureDefaultBadges();

        $badge = Badge::where('action_key', $actionKey)->first();

        if (!$badge) {
            return;
        }

        $alreadyEarned = UserBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->exists();

        if ($alreadyEarned) {
            return;
        }

        UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);

        $points = self::pointsFor($user);
        $points->points += $badge->points_required;
        $points->level = self::levelFor($points->points);
        $points->save();

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Badge debloque: ' . $badge->name,
            'message' => "Vous gagnez {$badge->points_required} points. Niveau actuel: {$points->level}.",
            'type' => 'success',
        ]);

        self::checkExpertBadge($user);
    }

    public static function ensureDefaultBadges(): void
    {
        foreach (self::BADGES as $badge) {
            Badge::updateOrCreate(
                ['action_key' => $badge['action_key']],
                $badge
            );
        }
    }

    private static function payloadFor(User $user): array
    {
        $earned = UserBadge::with('badge')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($userBadge) => $userBadge->badge->action_key);
        $points = self::pointsFor($user);
        $badges = Badge::orderBy('id')->get()->map(function ($badge) use ($earned) {
            $userBadge = $earned->get($badge->action_key);

            return [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'points' => $badge->points_required,
                'action_key' => $badge->action_key,
                'earned' => (bool) $userBadge,
                'earned_at' => $userBadge?->earned_at,
            ];
        });

        return [
            'points' => $points->points,
            'level' => $points->level,
            'next_level_points' => self::nextLevelPoints($points->points),
            'profile_completion' => self::profileCompletion($user),
            'badges' => $badges,
        ];
    }

    private static function pointsFor(User $user): UserPoint
    {
        return UserPoint::firstOrCreate(
            ['user_id' => $user->id],
            ['points' => 0, 'level' => 'Debutant']
        );
    }

    private static function checkProfileCompletion(User $user): void
    {
        if (self::profileCompletion($user) >= 100) {
            self::awardFor($user, 'profile_complete');
        }
    }

    private static function checkExpertBadge(User $user): void
    {
        $points = self::pointsFor($user);

        if ($points->points >= 500) {
            self::awardFor($user, 'expert');
        }
    }

    private static function profileCompletion(User $user): int
    {
        $user->loadMissing(['profile', 'educations', 'experiences', 'skills']);

        $checks = [
            filled($user->name),
            filled($user->email),
            filled($user->profile?->phone),
            filled($user->profile?->address),
            filled($user->profile?->summary),
            $user->educations->isNotEmpty(),
            $user->experiences->isNotEmpty(),
            $user->skills->isNotEmpty(),
        ];

        return (int) round((collect($checks)->filter()->count() / count($checks)) * 100);
    }

    private static function levelFor(int $points): string
    {
        if ($points >= 500) {
            return 'Expert';
        }

        if ($points >= 150) {
            return 'Intermediaire';
        }

        return 'Debutant';
    }

    private static function nextLevelPoints(int $points): int
    {
        if ($points < 150) {
            return 150;
        }

        if ($points < 500) {
            return 500;
        }

        return $points;
    }
}
