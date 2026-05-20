<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreSection;
use App\Models\NuruxploreProject;
use App\Services\GroqAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SectionController extends Controller
{
    protected GroqAIService $aiService;

    public function __construct(GroqAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index($projectId): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($projectId);
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sections = $project->sections()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->map(function ($section) {
                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'section_number' => $section->section_number,
                    'status' => $section->status,
                    'word_count' => $section->word_count,
                    'order' => $section->order,
                    'has_content' => !empty($section->content),
                    'children' => $section->children->map(fn($child) => [
                        'id' => $child->id,
                        'title' => $child->title,
                        'section_number' => $child->section_number,
                        'status' => $child->status,
                        'word_count' => $child->word_count,
                        'order' => $child->order,
                        'has_content' => !empty($child->content),
                    ]),
                ];
            });

        return response()->json([
            'sections' => $sections,
            'project_status' => $project->status,
            'total_word_count' => $project->word_count,
        ]);
    }

    public function show($id): JsonResponse
    {
        $section = NuruxploreSection::with(['children', 'parent', 'project'])->findOrFail($id);
        
        if ($section->project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'section' => $section,
            'project' => $section->project->only(['id', 'title', 'citation_style']),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::findOrFail($id);
        
        if ($section->project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|nullable|string',
            'section_number' => 'sometimes|nullable|string|max:20',
            'status' => 'sometimes|in:outlined,drafting,drafted,revising,complete',
            'order' => 'sometimes|integer',
        ]);

        $section->update($validated);
        $section->project->update(['last_edited_at' => now()]);

        return response()->json($section->fresh());
    }

    public function destroy($id): JsonResponse
    {
        $section = NuruxploreSection::findOrFail($id);
        
        if ($section->project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $section->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:nuruxplore_sections,id',
            'sections.*.order' => 'required|integer',
            'sections.*.parent_id' => 'nullable|exists:nuruxplore_sections,id',
        ]);

        foreach ($validated['sections'] as $sectionData) {
            NuruxploreSection::where('id', $sectionData['id'])->update([
                'order' => $sectionData['order'],
                'parent_id' => $sectionData['parent_id'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Sections reordered successfully']);
    }

    /**
     * AI-Powered Section Content Generation
     */
    public function aiGenerate(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::with('project')->findOrFail($id);
        $project = $section->project;

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

        // Build context
        $context = "Project: {$project->title}\n";
        $context .= "Type: {$project->type}\n";
        $context .= "Citation Style: {$project->citation_style}\n";
        $context .= "Section: {$section->title}\n";
        
        if ($project->research_question) {
            $context .= "Research Question: {$project->research_question}\n";
        }

        // Generate with AI
        $result = $this->aiService->generateSection(
            $section->title,
            $context,
            $project->citation_style
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 500);
        }

        // Deduct credits
        $user->deductCredits(5, 'Section generation: ' . $section->title, $project->id);

        // Update section
        $section->update([
            'content' => $result['content'],
            'status' => 'drafted',
            'ai_metadata' => [
                'model' => $result['model'] ?? 'groq',
                'tokens_used' => $result['tokens_used'] ?? 0,
                'generated_at' => now()->toISOString(),
            ],
        ]);

        $project->update(['last_edited_at' => now()]);

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Section generated successfully',
        ]);
    }

    public function aiRevise(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::with('project')->findOrFail($id);
        $project = $section->project;

        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'instruction' => 'required|string|max:500',
        ]);

        $user = $request->user();
        if ($user->credits_balance < 2) {
            return response()->json([
                'message' => 'Insufficient credits. You need 2 credits.',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        if (empty($section->content)) {
            return response()->json(['message' => 'Section has no content to revise. Generate content first.'], 400);
        }

        $result = $this->aiService->reviseContent(
            $section->content,
            $validated['instruction']
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 500);
        }

        $user->deductCredits(2, 'Section revision: ' . $section->title, $project->id);

        $section->update([
            'content' => $result['content'],
            'status' => 'revising',
        ]);

        $project->update(['last_edited_at' => now()]);

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Section revised successfully',
        ]);
    }
}