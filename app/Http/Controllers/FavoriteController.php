<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\JobOffer;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with('jobOffer')->get();
        return response()->json($favorites);
    }

    public function store(Request $request, JobOffer $job)
    {
        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'job_offer_id' => $job->id,
        ]);

        return response()->json($favorite);
    }

    public function destroy(Request $request, JobOffer $job)
    {
        $request->user()->favorites()->where('job_offer_id', $job->id)->delete();
        return response()->json(['message' => 'Favorite removed']);
    }
}
