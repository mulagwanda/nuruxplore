<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use App\Services\GroqAIService;
use App\Services\NuruAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    protected GroqAIService $aiService;
    protected NuruAIService $nuruAI;

    public function __construct(GroqAIService $aiService, NuruAIService $nuruAI)
    {
        $this->aiService = $aiService;
        $this->nuruAI = $nuruAI;
    }

    public function index(): JsonResponse
{
    $projects = request()->user()->projects()
        ->latest('last_edited_at')
        ->get()
        ->map(fn($project) => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'title' => $project->title,
            'type' => $project->type,
            'status' => $project->status,
            'word_count' => $project->word_count,
            'citation_style' => $project->citation_style,
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
        'uuid' => (string) Str::uuid(),
        'status' => 'draft',
        'last_edited_at' => now(),
    ]);

    // Create initial version (no sections reference)
    NuruxploreVersion::create([
        'project_id' => $project->id,
        'user_id' => $request->user()->id,
        'version_number' => 1,
        'snapshot' => ['content' => null, 'word_count' => 0],
        'changes_description' => 'Project created',
        'change_type' => 'manual',
    ]);

    return response()->json([
        'id' => $project->id,
        'uuid' => $project->uuid,
        'title' => $project->title,
        'message' => 'Project created successfully',
    ], 201);
}

    // show() - Laravel automatically resolves by UUID
    public function show(NuruxploreProject $project): JsonResponse
    {
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'title' => $project->title,
                'type' => $project->type,
                'citation_style' => $project->citation_style,
                'word_count' => $project->word_count,
                'status' => $project->status,
                'content' => $project->content, // ← Make sure this is here
                'structure' => $project->structure,
                'last_edited_at' => $project->last_edited_at?->diffForHumans(),
            ],
        ]);
    }

    // update() - Laravel automatically resolves by UUID
    public function update(Request $request, NuruxploreProject $project): JsonResponse
    {
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

    // destroy() - Laravel automatically resolves by UUID
    public function destroy(NuruxploreProject $project): JsonResponse
    {
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();
        return response()->json(null, 204);
    }

    // duplicate() - uses explicit UUID lookup
    public function duplicate($uuid): JsonResponse
    {
        $original = NuruxploreProject::where('uuid', $uuid)
            ->with('sections', 'sources')
            ->firstOrFail();

        if ($original->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $duplicate = $original->replicate();
        $duplicate->title = $original->title . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->last_edited_at = now();
        $duplicate->uuid = (string) Str::uuid();
        $duplicate->save();

        foreach ($original->sections as $section) {
            $newSection = $section->replicate();
            $newSection->project_id = $duplicate->id;
            $newSection->save();
        }

        return response()->json([
            'id' => $duplicate->id,
            'uuid' => $duplicate->uuid,
            'message' => 'Project duplicated successfully'
        ], 201);
    }

    // generateOutline() - uses UUID
    public function generateOutline(Request $request, $uuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $uuid)->firstOrFail();
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        if ($user->credits_balance < 5) {
            return response()->json([
                'message' => 'Insufficient credits. You need 5 credits.',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        $topic = $request->input('topic', $project->title);
        $result = $this->aiService->generateThesisOutline($topic, $project->type);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 500);
        }

        $outline = $this->parseOutlineFromAI($result['content']);
        $user->deductCredits(5, 'Outline generation', $project->id);
        $project->update(['structure' => $outline, 'last_edited_at' => now()]);
        
        NuruxploreSection::where('project_id', $project->id)->delete();
        $this->createSectionsFromOutline($project, $outline);

        return response()->json([
            'success' => true,
            'outline' => $outline,
            'sections' => $project->fresh()->sections()->whereNull('parent_id')->get()->values(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Outline generated successfully with ' . count($outline) . ' chapters.',
        ]);
    }

    // generateComplete() - uses UUID
    public function generateComplete(Request $request, NuruxploreProject $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        if ($user->credits_balance < 25) {
            return response()->json([
                'message' => 'Insufficient credits. Need 25 credits.', 
                'credits_balance' => $user->credits_balance
            ], 402);
        }

        $topic = $request->input('topic', $project->title);
        $user->deductCredits(25, 'Complete thesis generation', $project->id);
        
        $steps = $this->nuruAI->generateCompleteThesis($project, $topic);

        return response()->json([
            'success' => true,
            'steps' => $steps,
            'project_uuid' => $project->uuid,
            'project' => [
                'uuid' => $project->uuid,
                'title' => $project->fresh()->title,
                'content' => $project->fresh()->content,
                'word_count' => $project->fresh()->word_count,
            ],
            'credits_remaining' => $user->fresh()->credits_balance,
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