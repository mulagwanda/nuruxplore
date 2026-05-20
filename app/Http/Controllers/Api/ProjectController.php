<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use App\Services\GroqAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    protected GroqAIService $aiService;

    public function __construct(GroqAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index(): JsonResponse
    {
        $projects = request()->user()->projects()
            ->withCount('sections')
            ->latest('last_edited_at')
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'word_count' => $project->word_count,
                'citation_style' => $project->citation_style,
                'sections_count' => $project->sections_count,
                'last_edited_at' => $project->last_edited_at?->diffForHumans(),
                'created_at' => $project->created_at->format('M d, Y'),
            ]);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:thesis,dissertation,literature_review,lab_report,case_study,capstone',
            'citation_style' => 'in:APA7,MLA,Chicago,IEEE',
            'description' => 'nullable|string|max:1000',
            'research_question' => 'nullable|string|max:1000',
        ]);

        $project = $request->user()->projects()->create([
            ...$validated,
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        // Create initial version
        NuruxploreVersion::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'version_number' => 1,
            'snapshot' => ['project' => $project->toArray(), 'sections' => []],
            'changes_description' => 'Project created',
            'change_type' => 'manual',
        ]);

        return response()->json([
            'id' => $project->id,
            'title' => $project->title,
            'message' => 'Project created successfully',
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $project = NuruxploreProject::with([
            'sections' => fn($q) => $q->orderBy('order')->with('children'),
            'sources',
            'versions' => fn($q) => $q->latest()->limit(10)
        ])->findOrFail($id);

        // Authorization check
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'project' => $project,
            'outline' => $project->structure,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($id);

        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,in_progress,review,complete',
            'structure' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string|max:1000',
            'research_question' => 'sometimes|nullable|string|max:1000',
        ]);

        $project->update([...$validated, 'last_edited_at' => now()]);

        return response()->json($project);
    }

    public function destroy($id): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($id);

        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();

        return response()->json(null, 204);
    }

    public function duplicate($id): JsonResponse
    {
        $original = NuruxploreProject::with('sections', 'sources')->findOrFail($id);

        if ($original->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $duplicate = $original->replicate();
        $duplicate->title = $original->title . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->last_edited_at = now();
        $duplicate->save();

        foreach ($original->sections as $section) {
            $newSection = $section->replicate();
            $newSection->project_id = $duplicate->id;
            $newSection->save();
        }

        return response()->json([
            'id' => $duplicate->id,
            'message' => 'Project duplicated successfully'
        ], 201);
    }

    /**
     * AI-Powered Outline Generation
     */
    public function generateOutline(Request $request, $id): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($id);
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        if ($user->credits_balance < 5) {
            return response()->json([
                'message' => 'Insufficient credits. You need 5 credits to generate an outline.',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        $topic = $request->input('topic', $project->title);
        
        // Generate outline with AI
        $result = $this->aiService->generateThesisOutline($topic, $project->type);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'],
                'debug' => config('app.debug') ? $result : null,
            ], 500);
        }

        // Parse AI response
        $outline = $this->parseOutlineFromAI($result['content']);

        // Deduct credits
        $user->deductCredits(5, 'Outline generation', $project->id);

        // Save outline as project structure
        $project->update([
            'structure' => $outline,
            'last_edited_at' => now(),
        ]);

        // Delete existing sections before creating new ones
        \App\Models\NuruxploreSection::where('project_id', $project->id)->delete();

        // Create sections from outline
        $this->createSectionsFromOutline($project, $outline);

        // Create version
        $latestVersion = $project->versions()->max('version_number') ?? 0;
        \App\Models\NuruxploreVersion::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'version_number' => $latestVersion + 1,
            'snapshot' => [
                'project' => $project->fresh()->toArray(),
                'sections' => $project->sections()->get()->toArray(),
            ],
            'changes_description' => 'AI outline generated',
            'change_type' => 'ai_generation',
            'ai_interaction_log' => ['outline' => $outline],
        ]);

        // Return structured response
        return response()->json([
            'success' => true,
            'outline' => $outline,
            'sections' => $project->fresh()->sections()->whereNull('parent_id')->get()->values(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Outline generated successfully with ' . count($outline) . ' chapters.',
        ]);
    }

    protected function parseOutlineFromAI(string $aiResponse): array
    {
        // Log the raw response for debugging
        \Log::info('AI Outline Response:', ['response' => $aiResponse]);

        // Try multiple parsing strategies
        
        // Strategy 1: Direct JSON decode
        $cleanResponse = trim($aiResponse);
        $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $cleanResponse);
        
        $json = json_decode($cleanResponse, true);
        if ($json && isset($json['chapters'])) {
            return $json['chapters'];
        }
        
        // Strategy 2: Extract JSON from response
        if (preg_match('/\{.*"chapters".*\}/s', $aiResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['chapters'])) {
                return $json['chapters'];
            }
        }

        // Strategy 3: Fallback - parse markdown structure
        $lines = explode("\n", $aiResponse);
        $chapters = [];
        $currentChapter = null;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) continue;
            
            // Match chapter headers (## or ###)
            if (preg_match('/^#{2,3}\s+(.+)/', $line, $matches)) {
                if ($currentChapter) {
                    $chapters[] = $currentChapter;
                }
                $currentChapter = [
                    'title' => trim(str_replace(['**', '__'], '', $matches[1])),
                    'subsections' => [],
                ];
            }
            // Match list items (- or * or numbers)
            elseif (preg_match('/^[\s]*[-*\d+\.]\s+(.+)/', $line, $matches) && $currentChapter) {
                $currentChapter['subsections'][] = trim(str_replace(['**', '__'], '', $matches[1]));
            }
        }

        if ($currentChapter) {
            $chapters[] = $currentChapter;
        }

        // Strategy 4: If still empty, create a basic structure
        if (empty($chapters)) {
            $chapters = [
                [
                    'title' => 'Abstract',
                    'subsections' => ['Research Background', 'Methodology Overview', 'Key Findings', 'Conclusions']
                ],
                [
                    'title' => 'Introduction',
                    'subsections' => ['Background', 'Problem Statement', 'Research Questions', 'Significance']
                ],
                [
                    'title' => 'Literature Review',
                    'subsections' => ['Theoretical Framework', 'Empirical Studies', 'Research Gaps']
                ],
                [
                    'title' => 'Methodology',
                    'subsections' => ['Research Design', 'Data Collection', 'Analysis Methods']
                ],
                [
                    'title' => 'Results',
                    'subsections' => ['Descriptive Statistics', 'Main Findings', 'Analysis']
                ],
                [
                    'title' => 'Discussion',
                    'subsections' => ['Interpretation', 'Implications', 'Limitations']
                ],
                [
                    'title' => 'Conclusion',
                    'subsections' => ['Summary', 'Recommendations', 'Future Research']
                ]
            ];
        }

        return $chapters;
    }

    protected function createSectionsFromOutline(NuruxploreProject $project, array $outline): void
    {
        $order = 1;
        $chapterNum = 1;

        foreach ($outline as $chapter) {
            if (is_string($chapter)) {
                $chapter = ['title' => $chapter, 'subsections' => []];
            }

            // Create chapter
            $section = $project->sections()->create([
                'title' => $chapter['title'],
                'section_number' => (string) $chapterNum,
                'order' => $order++,
                'status' => 'outlined',
            ]);

            // Create subsections
            if (!empty($chapter['subsections'])) {
                $subOrder = 1;
                foreach ($chapter['subsections'] as $subNum => $subsection) {
                    if (is_string($subsection)) {
                        $subsectionTitle = $subsection;
                    } else {
                        $subsectionTitle = $subsection['title'] ?? $subsection;
                    }

                    $section->children()->create([
                        'project_id' => $project->id,
                        'title' => $subsectionTitle,
                        'section_number' => "{$chapterNum}." . ($subNum + 1),
                        'order' => $subOrder++,
                        'status' => 'outlined',
                    ]);
                }
            }

            $chapterNum++;
        }
    }

}