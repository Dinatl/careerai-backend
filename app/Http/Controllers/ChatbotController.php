<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;

class ChatbotController extends Controller
{
    public function history(Request $request)
    {
        return $request->user()
            ->chatMessages()
            ->latest()
            ->limit(30)
            ->get()
            ->reverse()
            ->values();
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'lang' => 'nullable|string'
        ]);

        $user = $request->user()->load(['profile', 'skills', 'quizResults', 'cvAnalyses']);
        $lang = $request->input('lang', 'fr');
        $langMap = ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'];
        $targetLang = $langMap[$lang] ?? 'French';
        $latestQuiz = $user->quizResults->sortByDesc('created_at')->first();
        $latestAts = $user->cvAnalyses->sortByDesc('created_at')->first();
        $skills = $user->skills->pluck('name')->join(', ') ?: 'Not provided yet';

        ChatMessage::create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message
        ]);

        try {
            $recentMessages = $user->chatMessages()
                ->latest()
                ->limit(8)
                ->get()
                ->reverse()
                ->map(fn ($message) => strtoupper($message->role) . ': ' . $message->message)
                ->join("\n");

            $prompt = "You are an expert career mentor. Respond in {$targetLang} with personalized, practical advice.\n\n"
                . "User profile:\n"
                . "- Name: {$user->name}\n"
                . "- RIASEC profile: " . ($latestQuiz?->personality_type ?? 'Not assessed yet') . "\n"
                . "- Skills: {$skills}\n"
                . "- Latest ATS score: " . ($latestAts?->score ?? 'Not analyzed yet') . "\n"
                . "- Summary: " . ($user->profile?->summary ?? 'Not provided yet') . "\n\n"
                . "Recent conversation:\n{$recentMessages}\n\n"
                . "User says: {$request->message}";

            $aiResponse = app(GeminiService::class)->generateContent($prompt);
        } catch (\Exception $e) {
            return response()->json(['message' => "AI Connection Error: " . $e->getMessage()], 500);
        }

        $assistantMsg = ChatMessage::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $aiResponse
        ]);

        return response()->json($assistantMsg);
    }
}
