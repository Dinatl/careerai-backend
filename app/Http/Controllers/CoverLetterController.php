<?php

namespace App\Http\Controllers;

use App\Models\CoverLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class CoverLetterController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'job_description' => 'required|string',
        ]);

        $prompt = "Write a highly professional, modern, and engaging cover letter targeted precisely for the following job description. Keep it concise and emphasize adaptability. Do not include random bracket placeholders for names (just write the body text seamlessly). Job Description: " . $request->job_description;

        try {
            $generatedContent = app(GeminiService::class)->generateContent($prompt);
        } catch (\Exception $e) {
             return response()->json(['message' => 'AI generation process failed: ' . $e->getMessage()], 500);
        }

        $coverLetter = CoverLetter::create([
            'user_id' => $request->user()->id,
            'job_offer_id' => $request->job_offer_id ?? null,
            'content' => $generatedContent
        ]);

        return response()->json($coverLetter);
    }
}
