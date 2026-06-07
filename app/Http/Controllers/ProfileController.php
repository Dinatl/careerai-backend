<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'profile' => $user->profile,
            'educations' => $user->educations,
            'experiences' => $user->experiences,
            'skills' => $user->skills,
            'quiz_results' => $user->quizResults,
            'favorites' => $user->favorites,
        ]);
    }

    public function update(Request $request)
    {
        $profile = Profile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only(['phone', 'address', 'summary'])
        );

        return response()->json($profile);
    }
}
