<?php

namespace App\Http\Controllers;

use App\Models\QuizResult;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class QuizController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'answers' => 'required|array',
        ]);

        $lang = $request->input('lang', 'en');
        $langMap = ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'];
        $targetLang = $langMap[$lang] ?? 'English';

        $answersStr = json_encode($request->answers);
        $prompt = 'Analyze these career quiz answers: ' . $answersStr . ' 
        Step 1: Map the answers to the Holland Code (RIASEC) framework: Realistic, Investigative, Artistic, Social, Enterprising, Conventional.
        Step 2: Respond in ' . $targetLang . '.
        Step 3: Generate a JSON response with the following structure:
        {
          "personality_type": "e.g., The Investigative Architect",
          "riasec_scores": {"R": 85, "I": 90, "A": 40, "S": 30, "E": 20, "C": 60},
          "analysis": "A brief psychological summary...",
          "recommended_jobs": [
            {
              "title": "Job name",
              "salary_range": "$XXk - $YYk",
              "match_percentage": 95,
              "roadmap": ["Step 1: Get cert X", "Step 2: Build portfolio"],
              "required_skills": ["Skill 1", "Skill 2"]
            }
          ]
        }
        Respond ONLY with the JSON object. No markdown, no backticks.';

        try {
            $text = app(GeminiService::class)->generateContent($prompt);
            $text = str_replace(['```json', '```'], '', $text);
            $parsed = json_decode(trim($text), true);
            
            if (!$parsed || !isset($parsed['personality_type'])) {
                throw new \Exception("AI generated invalid JSON: " . $text);
            }
            
            $personality = $parsed['personality_type'];
            $recommendedJobs = $parsed['recommended_jobs'] ?? [];
            // We can inject riasec_scores into the metadata or keep it in recommended_jobs
            $metadata = [
                'riasec_scores' => $parsed['riasec_scores'] ?? [],
                'analysis' => $parsed['analysis'] ?? '',
                'raw_answers' => $request->answers
            ];
        } catch (\Exception $e) {
            return response()->json(['message' => 'AI Framework Analysis Error: ' . $e->getMessage()], 500);
        }

        $result = QuizResult::create([
            'user_id' => $request->user()->id,
            'personality_type' => $personality,
            'recommended_jobs' => $recommendedJobs,
            'score_data' => $metadata,
        ]);

        Notification::create([
            'user_id' => $request->user()->id,
            'title' => 'Orientation Quiz Completed',
            'message' => "You've successfully completed your career assessment. Your profile: $personality",
            'type' => 'success'
        ]);

        BadgeController::awardFor($request->user(), 'quiz_completed');

        return response()->json($result);
    }
}
