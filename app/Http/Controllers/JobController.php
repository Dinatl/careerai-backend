<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = JobOffer::query();

        if ($domain = $request->input('domain')) {
            $query->where('domain', $domain);
        }

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
        }

        return response()->json($query->paginate(20));
    }

    public function show(JobOffer $job)
    {
        return response()->json($job);
    }
}
