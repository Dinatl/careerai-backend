<?php

namespace App\Http\Controllers;

use App\Models\Roadmap;
use App\Models\RoadmapProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class RoadmapController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('quizResults');
        $latestQuiz = $user->quizResults->sortByDesc('created_at')->first();
        $riasecType = $latestQuiz?->personality_type ?? 'Career Explorer';
        $roadmap = $user->roadmaps()
            ->where('riasec_type', $riasecType)
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->first();

        if (!$roadmap) {
            $steps = $this->generateSteps($riasecType, $latestQuiz?->recommended_jobs ?? []);
            $roadmap = Roadmap::create([
                'user_id' => $user->id,
                'riasec_type' => $riasecType,
                'steps' => $steps,
            ]);
        }

        $progress = RoadmapProgress::where('user_id', $user->id)
            ->where('roadmap_id', $roadmap->id)
            ->get()
            ->keyBy('step_id');

        $steps = collect($roadmap->steps)->map(function ($step, $index) use ($progress) {
            $stepId = $step['id'] ?? 'step-' . ($index + 1);
            $step['id'] = $stepId;
            $step['completed'] = (bool) ($progress->get($stepId)?->completed ?? false);
            return $step;
        })->values();

        return response()->json([
            'id' => $roadmap->id,
            'riasec_type' => $roadmap->riasec_type,
            'steps' => $steps,
            'created_at' => $roadmap->created_at,
        ]);
    }

    public function updateProgress(Request $request)
    {
        $request->validate([
            'roadmap_id' => 'required|exists:roadmaps,id',
            'step_id' => 'required|string',
            'completed' => 'required|boolean',
        ]);

        $roadmap = Roadmap::where('id', $request->roadmap_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $progress = RoadmapProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'roadmap_id' => $roadmap->id,
                'step_id' => $request->step_id,
            ],
            [
                'completed' => $request->completed,
                'completed_at' => $request->completed ? now() : null,
            ]
        );

        return response()->json($progress);
    }

    private function generateSteps(string $riasecType, array $recommendedJobs): array
    {
        $fallback = $this->fallbackSteps($recommendedJobs);
        $prompt = "Generate a personalized career roadmap in French for this RIASEC profile: {$riasecType}.\n"
            . "Recommended jobs: " . json_encode($recommendedJobs) . "\n"
            . "Return only a valid JSON array. Each item must have: id, title, timeline, focus, details, resources array, projects array.";

        try {
            $text = app(GeminiService::class)->generateContent($prompt);
            $text = trim(str_replace(['```json', '```'], '', $text));
            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                return $fallback;
            }

            return collect($decoded)->values()->map(function ($step, $index) {
                return [
                    'id' => $step['id'] ?? 'step-' . ($index + 1),
                    'title' => $step['title'] ?? 'Etape ' . ($index + 1),
                    'timeline' => $step['timeline'] ?? 'A definir',
                    'focus' => $step['focus'] ?? 'Progression carriere',
                    'details' => $step['details'] ?? '',
                    'resources' => $step['resources'] ?? [],
                    'projects' => $step['projects'] ?? [],
                ];
            })->all();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function fallbackSteps(array $recommendedJobs): array
    {
        $target = $recommendedJobs[0]['title'] ?? 'metier cible';

        return [
            [
                'id' => 'foundation',
                'title' => 'Clarifier le poste cible',
                'timeline' => '0-1 mois',
                'focus' => 'Positionnement',
                'details' => "Choisir un poste prioritaire comme {$target}, analyser 10 offres et lister les competences recurrentes.",
                'resources' => ['LinkedIn Jobs', 'Onisep', 'France Travail'],
                'projects' => ['Creer une matrice competences vs offres'],
            ],
            [
                'id' => 'skills',
                'title' => 'Construire le socle technique',
                'timeline' => '1-3 mois',
                'focus' => 'Competences',
                'details' => 'Apprendre les outils et methodes les plus demandes, puis documenter les acquis dans le CV.',
                'resources' => ['Coursera', 'OpenClassrooms', 'Google Career Certificates'],
                'projects' => ['Publier un mini-projet mesurable sur GitHub ou portfolio'],
            ],
            [
                'id' => 'certification',
                'title' => 'Ajouter une preuve externe',
                'timeline' => '3-6 mois',
                'focus' => 'Certifications',
                'details' => 'Valider une certification ou formation courte reliee au poste cible.',
                'resources' => ['Meta Careers Certificates', 'Microsoft Learn', 'AWS Skill Builder'],
                'projects' => ['Ajouter une etude de cas avec objectifs, actions et resultats'],
            ],
            [
                'id' => 'market',
                'title' => 'Activer la candidature',
                'timeline' => '6-12 mois',
                'focus' => 'Marche',
                'details' => 'Adapter CV, portfolio et pitch entretien pour chaque offre prioritaire.',
                'resources' => ['Portfolio CareerAI', 'Simulateur entretien', 'Score ATS'],
                'projects' => ['Candidater a 20 offres qualifiees et suivre les retours'],
            ],
        ];
    }
}
