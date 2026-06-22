<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreSection;
use App\Services\NuruAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(protected NuruAIService $nuruAI)
    {
    }

    public function index($projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        $this->authorizeProject($project);

        $sections = $project->topLevelSections()
            ->with('children')
            ->get()
            ->map(fn ($section) => $this->sectionPayload($section, true));

        return response()->json([
            'sections' => $sections,
            'project_status' => $project->status,
            'research_profile_status' => $project->research_profile_status ?? 'missing',
            'total_word_count' => $project->word_count,
        ]);
    }

    public function store(Request $request, $projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        $this->authorizeProject($project, $request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'section_number' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:nuruxplore_sections,id',
            'order' => 'nullable|integer',
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = NuruxploreSection::findOrFail($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Parent section does not belong to this project.');
        }

        $maxOrder = NuruxploreSection::where('project_id', $project->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('order') ?? 0;

        $section = $project->sections()->create([
            'title' => $validated['title'],
            'section_number' => $validated['section_number'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'order' => $validated['order'] ?? $maxOrder + 1,
            'status' => 'outlined',
        ]);

        $project->update(['last_edited_at' => now()]);

        return response()->json($this->sectionPayload($section), 201);
    }

    public function show($id): JsonResponse
    {
        $section = NuruxploreSection::with(['children', 'parent', 'project'])->findOrFail($id);
        $this->authorizeProject($section->project);

        return response()->json([
            'section' => $this->sectionPayload($section, true),
            'project' => [
                'id' => $section->project->id,
                'uuid' => $section->project->uuid,
                'title' => $section->project->title,
                'citation_style' => $section->project->citation_style,
                'research_profile_status' => $section->project->research_profile_status,
            ],
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::with('project')->findOrFail($id);
        $this->authorizeProject($section->project, $request);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|nullable|string',
            'section_number' => 'sometimes|nullable|string|max:20',
            'status' => 'sometimes|in:outlined,drafting,drafted,revising,complete',
            'order' => 'sometimes|integer',
            'ai_metadata' => 'sometimes|nullable|array',
        ]);

        $section->update($validated);
        $section->project->update(['last_edited_at' => now()]);

        return response()->json($this->sectionPayload($section->fresh()));
    }

    public function destroy($id): JsonResponse
    {
        $section = NuruxploreSection::with('project')->findOrFail($id);
        $this->authorizeProject($section->project);
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

        $first = NuruxploreSection::with('project')->findOrFail($validated['sections'][0]['id']);
        $this->authorizeProject($first->project, $request);

        foreach ($validated['sections'] as $sectionData) {
            $section = NuruxploreSection::findOrFail($sectionData['id']);
            abort_if($section->project_id !== $first->project_id, 422, 'All reordered sections must belong to the same project.');

            if (!empty($sectionData['parent_id'])) {
                $parent = NuruxploreSection::findOrFail($sectionData['parent_id']);
                abort_if($parent->project_id !== $first->project_id, 422, 'Parent section does not belong to this project.');
                abort_if((int) $sectionData['parent_id'] === (int) $section->id, 422, 'A section cannot be its own parent.');
            }

            $section->update([
                'order' => $sectionData['order'],
                'parent_id' => $sectionData['parent_id'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Sections reordered successfully']);
    }

    /**
     * Generate one section.
     * The AI service now returns body-only content; headings are owned by assembler/exporter.
     */
    public function aiGenerate(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::with(['project', 'parent'])->findOrFail($id);
        $project = $section->project;
        $this->authorizeProject($project, $request);

        $validated = $request->validate([
            'instruction' => 'nullable|string|max:1000',
        ]);

        if (empty($project->research_profile)) {
            return response()->json([
                'message' => 'Build and approve the research profile before generating sections.',
            ], 422);
        }

        $user = $request->user();
        $cost = 5;
        if ($user->credits_balance < $cost) {
            return response()->json([
                'message' => 'Insufficient credits. You need 5 credits.',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        $section->update(['status' => 'drafting']);

        $result = $this->nuruAI->generateSectionFromProfile(
            $section->fresh(['project', 'parent']),
            $validated['instruction'] ?? null
        );

        if (!$result['success']) {
            $section->update(['status' => blank($section->content) ? 'outlined' : 'drafted']);
            return response()->json([
                'message' => $result['error'] ?? 'Section generation failed.',
            ], 500);
        }

        $user->deductCredits($cost, 'Section generation: ' . $section->title, $project->id);

        return response()->json([
            'success' => true,
            'section' => $this->sectionPayload($section->fresh()),
            'credits_remaining' => $user->fresh()->credits_balance,
            'quality_flags' => $result['quality_flags'] ?? [],
            'message' => 'Section generated successfully',
        ]);
    }

    /**
     * Revise one section only; does not rewrite the whole thesis.
     */
    public function aiRevise(Request $request, $id): JsonResponse
    {
        $section = NuruxploreSection::with(['project', 'parent'])->findOrFail($id);
        $project = $section->project;
        $this->authorizeProject($project, $request);

        $validated = $request->validate([
            'instruction' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $cost = 2;
        if ($user->credits_balance < $cost) {
            return response()->json([
                'message' => 'Insufficient credits. You need 2 credits.',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        if (blank($section->content)) {
            return response()->json([
                'message' => 'Section has no content to revise. Generate content first.',
            ], 400);
        }

        $section->update(['status' => 'revising']);

        $result = $this->nuruAI->reviseSection($section->fresh(['project', 'parent']), $validated['instruction']);

        if (!$result['success']) {
            $section->update(['status' => 'drafted']);
            return response()->json([
                'message' => $result['error'] ?? 'Section revision failed.',
            ], 500);
        }

        $user->deductCredits($cost, 'Section revision: ' . $section->title, $project->id);

        return response()->json([
            'success' => true,
            'section' => $this->sectionPayload($section->fresh()),
            'credits_remaining' => $user->fresh()->credits_balance,
            'quality_flags' => $result['quality_flags'] ?? [],
            'message' => 'Section revised successfully',
        ]);
    }

    protected function sectionPayload(NuruxploreSection $section, bool $withChildren = false): array
    {
        $payload = [
            'id' => $section->id,
            'title' => $section->title,
            'section_number' => $section->section_number,
            'content' => $section->content,
            'status' => $section->status,
            'word_count' => $section->word_count,
            'order' => $section->order,
            'parent_id' => $section->parent_id,
            'has_content' => !empty($section->content),
            'ai_metadata' => $section->ai_metadata,
        ];

        if ($withChildren) {
            $payload['children'] = $section->children
                ->sortBy('order')
                ->map(fn ($child) => $this->sectionPayload($child))
                ->values();
        }

        return $payload;
    }

    protected function authorizeProject(NuruxploreProject $project, ?Request $request = null): void
    {
        $user = $request?->user() ?? request()->user();
        abort_if(!$user || $project->user_id !== $user->id, 403, 'Unauthorized');
    }
}
