<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\CVController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\CoverLetterController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CVFeedbackController;
use App\Http\Controllers\InterviewPrepController;
use App\Http\Controllers\SkillsAnalysisController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\CVIntelligenceController;
use App\Http\Controllers\InterviewHistoryController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\RoadmapController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/portfolio/{username}', [PortfolioController::class, 'showPublic']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
    
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{job}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{job}', [FavoriteController::class, 'destroy']);
    
    Route::get('/cv/export', [CVController::class, 'export']);
    Route::post('/cv/feedback', [CVFeedbackController::class, 'analyze']);
    Route::post('/cv/analyze', [CVIntelligenceController::class, 'analyze']);
    Route::post('/cv/compare', [CVIntelligenceController::class, 'compare']);
    
    Route::post('/interview/generate', [InterviewPrepController::class, 'generate']);
    Route::get('/interviews', [InterviewHistoryController::class, 'index']);
    Route::post('/interviews', [InterviewHistoryController::class, 'store']);
    Route::get('/interviews/{session}', [InterviewHistoryController::class, 'show']);
    
    Route::post('/skills/analyze', [SkillsAnalysisController::class, 'analyze']);
    
    Route::post('/quiz', [QuizController::class, 'store']);
    Route::get('/roadmap', [RoadmapController::class, 'show']);
    Route::patch('/roadmap/progress', [RoadmapController::class, 'updateProgress']);
    
    Route::post('/cover-letter', [CoverLetterController::class, 'generate']);
    
    Route::get('/badges', [BadgeController::class, 'index']);
    Route::post('/badges/check', [BadgeController::class, 'check']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/portfolio', [PortfolioController::class, 'settings']);
    Route::put('/portfolio', [PortfolioController::class, 'update']);
    Route::get('/chat/history', [ChatbotController::class, 'history']);
    Route::post('/chat', [ChatbotController::class, 'chat']);
});
