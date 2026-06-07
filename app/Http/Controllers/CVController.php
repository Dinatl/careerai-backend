<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CVController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user()->load(['profile', 'educations', 'experiences', 'skills']);
        
        // PDF Export placeholder using dompdf
        return response()->json([
            'message' => 'PDF generation currently mocked until dependencies are finalized.',
            'data' => $user
        ]);
    }
}
