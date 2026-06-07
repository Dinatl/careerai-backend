<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortfolioController extends Controller
{
    public function settings(Request $request)
    {
        return response()->json($this->portfolioFor($request->user()));
    }

    public function update(Request $request)
    {
        $portfolio = $this->portfolioFor($request->user());

        $request->validate([
            'is_public' => 'required|boolean',
            'custom_url' => [
                'required',
                'alpha_dash',
                'min:3',
                'max:60',
                Rule::unique('portfolios', 'custom_url')->ignore($portfolio->id),
            ],
        ]);

        $portfolio->update([
            'is_public' => $request->boolean('is_public'),
            'custom_url' => Str::slug($request->custom_url),
        ]);

        return response()->json($portfolio);
    }

    public function showPublic(string $username)
    {
        $portfolio = Portfolio::where('custom_url', $username)->first();

        if (!$portfolio) {
            return response()->json(['message' => 'Portfolio is private or not found.'], 404);
        }

        $portfolio->increment('views_count');
        $user = User::with(['profile', 'educations', 'experiences', 'skills', 'cvAnalyses'])
            ->findOrFail($portfolio->user_id);

        return response()->json([
            'portfolio' => $portfolio->fresh(),
            'user' => $user,
            'profile' => $user->profile,
            'educations' => $user->educations,
            'experiences' => $user->experiences,
            'skills' => $user->skills,
            'latest_cv_analysis' => $user->cvAnalyses()->latest()->first(),
        ]);
    }

    private function portfolioFor(User $user): Portfolio
    {
        return Portfolio::firstOrCreate(
            ['user_id' => $user->id],
            ['custom_url' => $this->defaultSlug($user), 'is_public' => true]
        );
    }

    private function defaultSlug(User $user): string
    {
        $base = Str::slug($user->name) ?: 'careerai';
        $slug = $base;
        $i = 1;

        while (Portfolio::where('custom_url', $slug)->where('user_id', '!=', $user->id)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
