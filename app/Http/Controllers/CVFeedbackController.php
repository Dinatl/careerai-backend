<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class CVFeedbackController extends Controller
{
    public function analyze(Request $request)
    {
        $request->validate([
            'cv_data' => 'required|array',
        ]);

        $lang = $request->input('lang', 'en');
        $langMap = ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'];
        $targetLang = $langMap[$lang] ?? 'English';

        $cvDataStr = json_encode($request->cv_data);
        $prompt = "Analyze this CV data: $cvDataStr. 
        Respond in {$targetLang}.
        Provide constructive feedback to improve it for modern ATS systems and recruiters.
        Focus on:
        1. Action verbs usage.
        2. Quantifiable results.
        3. Skills gap or missing sections.
        4. Summary strength.
        
        Respond ONLY with a JSON object:
        {
          \"overall_score\": 85,
          \"strengths\": [\"...\", \"...\"],
          \"improvements\": [\"...\", \"...\"],
          \"ats_suggestions\": \"...\"
        }";

        try {
            $text = app(GeminiService::class)->generateContent($prompt);
            $text = str_replace(['```json', '```'], '', $text);
            $parsed = json_decode(trim($text), true);

            return response()->json($parsed);
        } catch (\Exception $e) {
            return response()->json(['message' => 'CV Analysis Failed: ' . $e->getMessage()], 500);
        }
    }
}
