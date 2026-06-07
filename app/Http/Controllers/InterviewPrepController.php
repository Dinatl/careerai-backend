<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class InterviewPrepController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'job_title' => 'required|string',
            'level' => 'required|string', // e.g., Junior, Senior
        ]);

        $lang = $request->input('lang', 'en');
        $langMap = ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'];
        $targetLang = $langMap[$lang] ?? 'English';

        $prompt = "Generate 5 tailored interview questions for a {$request->level} {$request->job_title} position. 
        Respond in {$targetLang}.
        For each question, provide:
        1. Why the interviewer is asking this.
        2. A sample 'Star' answer outline.
        3. Key keywords to include.
        
        Respond ONLY with a JSON array of objects:
        [
          {
            \"question\": \"...\",
            \"rationale\": \"...\",
            \"sample_outline\": \"...\",
            \"keywords\": [\"...\", \"...\"]
          }
        ]";

        try {
            $text = app(GeminiService::class)->generateContent($prompt);
            $text = str_replace(['```json', '```'], '', $text);
            $parsed = json_decode(trim($text), true);

            return response()->json($parsed);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Interview Prep Generation Failed: ' . $e->getMessage()], 500);
        }
    }
}
