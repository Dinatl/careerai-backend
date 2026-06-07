<?php

namespace App\Http\Controllers;

use App\Models\InterviewSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterviewHistoryController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->interviewSessions()
            ->withCount('questions')
            ->latest()
            ->get();
    }

    public function show(Request $request, InterviewSession $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($session->load('questions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'job_title' => 'required|string|max:255',
            'overall_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.user_answer' => 'nullable|string',
            'questions.*.ai_feedback' => 'nullable|string',
            'questions.*.star_score' => 'nullable|integer|min:0|max:100',
        ]);

        $session = DB::transaction(function () use ($request) {
            $session = InterviewSession::create([
                'user_id' => $request->user()->id,
                'job_title' => $request->job_title,
                'overall_score' => $request->input('overall_score', 0),
            ]);

            foreach ($request->questions as $question) {
                $session->questions()->create([
                    'question' => $question['question'],
                    'user_answer' => $question['user_answer'] ?? null,
                    'ai_feedback' => $question['ai_feedback'] ?? null,
                    'star_score' => $question['star_score'] ?? 0,
                ]);
            }

            return $session;
        });

        BadgeController::awardFor($request->user(), 'simulated_interview');

        return response()->json($session->load('questions'), 201);
    }
}
