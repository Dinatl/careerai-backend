<?php

namespace App\Http\Controllers;

use App\Models\CvAnalysis;
use App\Models\CvComparison;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;

class CVIntelligenceController extends Controller
{
    public function analyze(Request $request)
    {
        $request->validate([
            'cv' => 'nullable|file|mimes:pdf|max:8192',
            'cv_text' => 'nullable|string',
            'lang' => 'nullable|string',
        ]);

        if (!$request->hasFile('cv') && !$request->filled('cv_text')) {
            return response()->json(['message' => 'Upload a PDF CV or provide cv_text.'], 422);
        }

        $file = $request->file('cv');
        $cvText = trim((string) $request->input('cv_text', ''));
        $mediaParts = [];

        if ($file) {
            // Read PDF as base64 for Gemini multimodal input
            $mediaParts[] = [
                'mimeType' => 'application/pdf',
                'data' => base64_encode(file_get_contents($file->getRealPath()))
            ];

            // Extract text as a backup / database field
            $cvText = trim($this->extractPdfText($file->getRealPath()));
        }

        $fallback = $this->fallbackCvAnalysis($cvText);
        $targetLang = $this->targetLanguage($request->input('lang', 'fr'));
        $prompt = "Analyse ce CV selon les criteres ATS suivants, puis reponds en {$targetLang} uniquement avec un JSON valide:\n"
            . "1. Mots-cles metier presents\n"
            . "2. Verbes d'action utilises\n"
            . "3. Resultats quantifiables\n"
            . "4. Format et structure\n"
            . "5. Longueur appropriee\n\n"
            . "Schema attendu:\n"
            . "{\n"
            . "  \"score\": 78,\n"
            . "  \"strengths\": [\"...\"],\n"
            . "  \"weaknesses\": [\"...\"],\n"
            . "  \"suggestions\": [\"...\"],\n"
            . "  \"extracted_text\": \"Texte complet extrait du CV sans aucune modification pour indexation...\"\n"
            . "}\n\n";

        if ($cvText !== '') {
            $prompt .= "CV:\n{$cvText}";
        }

        $analysis = $this->askGeminiForJson($prompt, $fallback, $mediaParts);
        $analysis['score'] = $this->clampScore($analysis['score'] ?? $fallback['score']);

        // Save the extracted text from Gemini (OCR capability) or use the fallback
        $savedCvText = !empty($analysis['extracted_text']) ? trim($analysis['extracted_text']) : $cvText;

        if ($savedCvText === '') {
            $savedCvText = 'PDF uploaded: ' . ($file?->getClientOriginalName() ?? 'CV') .
                '. Text extraction is unavailable until smalot/pdfparser is installed with composer install.';
        }

        $record = CvAnalysis::create([
            'user_id' => $request->user()->id,
            'original_filename' => $file?->getClientOriginalName(),
            'cv_text' => $savedCvText,
            'score' => $analysis['score'],
            'strengths' => $analysis['strengths'] ?? [],
            'weaknesses' => $analysis['weaknesses'] ?? [],
            'suggestions' => $analysis['suggestions'] ?? [],
        ]);

        BadgeController::awardFor($request->user(), 'ats_analysis');

        Notification::create([
            'user_id' => $request->user()->id,
            'title' => 'Score ATS calcule',
            'message' => "Votre CV obtient {$record->score}/100 avec des recommandations personnalisees.",
            'type' => 'success',
        ]);

        return response()->json($record);
    }

    public function compare(Request $request)
    {
        $request->validate([
            'job_offer_text' => 'required|string|min:20',
            'cv_text' => 'nullable|string',
            'lang' => 'nullable|string',
        ]);

        $user = $request->user()->load(['profile', 'experiences', 'educations', 'skills']);
        $cvText = trim((string) $request->input('cv_text', ''));
        $latestCv = $user->cvAnalyses()->latest()->first();

        if ($cvText === '') {
            $cvText = $latestCv?->cv_text ?: $this->buildCvText($user);
        }

        $jobOffer = $request->job_offer_text;
        $fallback = $this->fallbackComparison($cvText, $jobOffer);
        $targetLang = $this->targetLanguage($request->input('lang', 'fr'));
        $prompt = "Compare ce CV avec cette offre d'emploi et reponds en {$targetLang} uniquement avec un JSON valide.\n"
            . "Retourne ce schema:\n"
            . "{\"match_score\":65,\"matched_skills\":[\"...\"],\"missing_skills\":[\"...\"],\"recommendations\":[\"...\"]}\n\n"
            . "CV:\n{$cvText}\n\nOffre:\n{$jobOffer}";

        $comparison = $this->askGeminiForJson($prompt, $fallback);
        $comparison['match_score'] = $this->clampScore($comparison['match_score'] ?? $fallback['match_score']);

        $record = CvComparison::create([
            'user_id' => $user->id,
            'job_offer_text' => $jobOffer,
            'match_score' => $comparison['match_score'],
            'matched_skills' => $comparison['matched_skills'] ?? [],
            'missing_skills' => $comparison['missing_skills'] ?? [],
            'recommendations' => $comparison['recommendations'] ?? [],
        ]);

        return response()->json($record);
    }

    private function extractPdfText(string $path): string
    {
        $parserClass = \Smalot\PdfParser\Parser::class;

        if (!class_exists($parserClass)) {
            Log::warning("PDF parser class {$parserClass} does not exist.");
            return '';
        }

        try {
            $parser = new $parserClass();
            return $parser->parseFile($path)->getText();
        } catch (\Throwable $e) {
            Log::error("PDF text extraction failed: " . $e->getMessage(), [
                'exception' => $e,
                'path' => $path
            ]);
            return '';
        }
    }

    private function askGeminiForJson(string $prompt, array $fallback, array $mediaParts = []): array
    {
        try {
            $text = app(GeminiService::class)->generateContent($prompt, $mediaParts);
            $parsed = $this->parseJson($text);

            return is_array($parsed) ? array_merge($fallback, $parsed) : $fallback;
        } catch (\Throwable $e) {
            Log::error("CVIntelligenceController askGeminiForJson failed: " . $e->getMessage());
            return $fallback;
        }
    }

    private function parseJson(string $text): ?array
    {
        $text = trim(str_replace(['```json', '```'], '', $text));
        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function fallbackCvAnalysis(string $text): array
    {
        $lower = mb_strtolower($text);
        $score = 45;

        if (preg_match('/\d+%|\b\d+\b/', $text)) {
            $score += 12;
        }

        if (preg_match('/managed|built|created|improved|optimized|launched|dirige|cree|ameliore|optimise|developpe/i', $text)) {
            $score += 12;
        }

        if (str_contains($lower, 'experience') || str_contains($lower, 'education') || str_contains($lower, 'skills') || str_contains($lower, 'competences')) {
            $score += 12;
        }

        if (strlen($text) > 1200) {
            $score += 10;
        }

        return [
            'score' => $this->clampScore($score),
            'strengths' => [
                'Structure globale exploitable par un ATS.',
                'Presence de sections professionnelles utiles au recruteur.',
            ],
            'weaknesses' => [
                'Ajoutez plus de mots-cles exacts lies au poste vise.',
                'Transformez les responsabilites en resultats chiffres.',
            ],
            'suggestions' => [
                'Commencez les bullets par des verbes d action forts.',
                'Ajoutez des technologies, outils et certifications sous forme de mots-cles simples.',
                'Gardez un format sobre sans tableaux complexes pour faciliter le parsing ATS.',
            ],
        ];
    }

    private function fallbackComparison(string $cvText, string $jobOffer): array
    {
        $cvTerms = $this->extractSkillTerms($cvText);
        $jobTerms = $this->extractSkillTerms($jobOffer);
        $matched = array_values(array_intersect($jobTerms, $cvTerms));
        $missing = array_values(array_diff($jobTerms, $cvTerms));
        $score = count($jobTerms) > 0 ? round((count($matched) / count($jobTerms)) * 100) : 45;

        return [
            'match_score' => $this->clampScore($score),
            'matched_skills' => array_slice($matched, 0, 10),
            'missing_skills' => array_slice($missing, 0, 10),
            'recommendations' => [
                'Reprenez les mots-cles manquants dans votre resume et vos experiences si vous les maitrisez vraiment.',
                'Ajoutez un mini-projet ou une certification pour chaque competence critique absente.',
                'Priorisez les competences qui reviennent plusieurs fois dans l offre.',
            ],
        ];
    }

    private function extractSkillTerms(string $text): array
    {
        $knownSkills = [
            'javascript', 'typescript', 'react', 'vue', 'angular', 'laravel', 'php', 'python',
            'java', 'spring', 'node', 'sql', 'mysql', 'postgresql', 'mongodb', 'docker',
            'kubernetes', 'aws', 'azure', 'git', 'figma', 'seo', 'crm', 'excel',
            'communication', 'leadership', 'agile', 'scrum', 'project management',
            'machine learning', 'data analysis', 'api', 'rest', 'graphql',
        ];

        $lower = mb_strtolower($text);

        return collect($knownSkills)
            ->filter(fn ($skill) => str_contains($lower, $skill))
            ->values()
            ->all();
    }

    private function buildCvText($user): string
    {
        return collect([
            'Name: ' . $user->name,
            'Summary: ' . ($user->profile?->summary ?? ''),
            'Skills: ' . $user->skills->pluck('name')->join(', '),
            'Experiences: ' . $user->experiences->map(fn ($experience) => "{$experience->job_title} at {$experience->company}: {$experience->description}")->join("\n"),
            'Education: ' . $user->educations->map(fn ($education) => "{$education->degree}, {$education->school} ({$education->year})")->join("\n"),
        ])->filter()->join("\n");
    }

    private function targetLanguage(string $lang): string
    {
        return ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'][$lang] ?? 'French';
    }

    private function clampScore($value): int
    {
        return max(0, min(100, (int) round((float) $value)));
    }
}
